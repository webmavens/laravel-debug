<?php

namespace Webmavens\DebugMonitor\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webmavens\DebugMonitor\Jobs\RunDebugRuleJob;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Services\RuleRunner;
use Webmavens\DebugMonitor\Services\RuleSyncService;

class DebugRuleController extends Controller
{
    /**
     * Session key holding the rules list's last query string (page + filters),
     * so actions and "Go Back" links return to the same list view.
     */
    private const INDEX_QUERY_KEY = 'debug-monitor.rules_index_query';

    private const BULK_ACTIONS = ['run', 'export', 'activate', 'deactivate', 'suppress', 'unsuppress', 'delete'];

    public function index(Request $request, RuleSyncService $service)
    {
        $level = $request->importance_level;
        $allowedLevels = ['high', 'medium', 'low'];

        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status');

        $rules = DebugRule::query()
            // Prefix match (name LIKE 'term%') so the name index can serve the search.
            ->when($search !== '', fn ($query) => $query->where('name', 'like', $search.'%'))
            ->when(in_array($level, $allowedLevels), fn ($query) => $query->where('importance_level', $level))
            ->when($status === 'active', fn ($query) => $query->where('status', 'active'))
            ->when($status === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when($status === 'suppressed', fn ($query) => $query->where('suppress', true))
            // Active rules whose latest run failed (a deactivated rule is paused, not failing).
            ->when($status === 'failing', fn ($query) => $query->where('status', 'active')->where('last_status', 'failed'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // A page past the end (e.g. after deleting the last rule on the last
        // page) would render empty: send the user to the last real page instead.
        if ($rules->isEmpty() && $rules->currentPage() > 1) {
            // Keep the flash message (e.g. "Deleted 2 rule(s).") across this second redirect.
            $request->session()->reflash();

            return redirect()->route(
                'debug-monitor.rules.index',
                array_merge($request->query(), ['page' => $rules->lastPage()])
            );
        }

        $request->session()->put(self::INDEX_QUERY_KEY, $request->query());

        $fileRuleIds = $service->fileRuleIds();

        $pendingCount = $service->pendingCount();

        return view('debug-monitor::rules.index', compact('rules', 'fileRuleIds', 'pendingCount'));
    }

    public function create()
    {
        $indexUrl = $this->indexUrl();

        return view('debug-monitor::rules.form', compact('indexUrl'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        DebugRule::create($this->normalizeValidatedRule($validated));

        // New rules are listed first, so keep the filters but go to page 1.
        return $this->redirectToIndex(keepPage: false)
            ->with('success', 'Rule added successfully!');
    }

    public function sync(RuleSyncService $service)
    {
        $summary = $service->sync();

        $parts = [];

        if (! empty($summary['applied'])) {
            $parts[] = 'Imported: '.implode(', ', $summary['applied']);
        }

        if (! empty($summary['updated'])) {
            $parts[] = 'Updated: '.implode(', ', $summary['updated']);
        }

        if (! empty($summary['unlinked'])) {
            $parts[] = 'Unlinked (file removed): '.implode(', ', $summary['unlinked']);
        }

        if (! empty($summary['skipped'])) {
            $parts[] = 'Skipped (name exists): '.implode(', ', array_column($summary['skipped'], 'name'));
        }

        if (! empty($summary['errors'])) {
            $parts[] = 'Failed: '.implode(', ', array_column($summary['errors'], 'file'));
        }

        $message = empty($parts)
            ? 'Rule sync complete: everything is already up to date.'
            : 'Rule sync complete. '.implode('. ', $parts).'.';

        $status = empty($summary['errors']) ? 'success' : 'error';

        return $this->redirectToIndex()->with($status, $message);
    }

    public function export($id, RuleSyncService $service)
    {
        $rule = DebugRule::findOrFail($id);

        $result = $service->export($rule);

        $verb = $result['created'] ? 'exported to' : 'updated';

        return $this->redirectToIndex()
            ->with('success', "Rule \"{$rule->name}\" {$verb} file: {$result['file']}");
    }

    public function run($id, RuleRunner $runner)
    {
        $rule = DebugRule::findOrFail($id);

        // Manual runs stay silent (no notifications) — they're for testing rules.
        $outcome = $runner->run($rule, notify: false);

        if ($outcome['status'] === 'ok') {
            return back()->with('success', "Rule \"{$rule->name}\" ran and passed.");
        }

        $reason = $outcome['exception']
            ? "errored: {$outcome['exception']}"
            : 'failed — see the rule\'s logs for details.';

        return back()->with('error', "Rule \"{$rule->name}\" {$reason}");
    }

    public function toggleStatus($id, RuleSyncService $service)
    {
        $rule = DebugRule::findOrFail($id);

        $rule->update(['status' => $rule->status === 'active' ? 'inactive' : 'active']);

        // Status is part of the rule file, so keep a linked file in step.
        $service->writeLinkedFile($rule);

        return back()->with('success', "Rule \"{$rule->name}\" is now {$rule->status}.");
    }

    /**
     * Apply one action to the rules selected on the current page of the list.
     */
    public function bulk(Request $request, RuleSyncService $service)
    {
        $action = $request->input('action');
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        if (! in_array($action, self::BULK_ACTIONS, true) || empty($ids)) {
            return $this->redirectToIndex()->with('error', 'Select at least one rule and an action.');
        }

        $rules = DebugRule::whereIn('id', $ids)->get();

        if ($rules->isEmpty()) {
            return $this->redirectToIndex()->with('error', 'The selected rules no longer exist.');
        }

        [$type, $message] = match ($action) {
            'run' => $this->bulkRun($rules),
            'export' => $this->bulkExport($rules, $service),
            'activate' => $this->bulkStatus($rules, 'active', $service),
            'deactivate' => $this->bulkStatus($rules, 'inactive', $service),
            'suppress' => $this->bulkSuppress($rules, true),
            'unsuppress' => $this->bulkSuppress($rules, false),
            'delete' => $this->bulkDelete($rules, $service),
        };

        return $this->redirectToIndex()->with($type, $message);
    }

    /** @return array{0: string, 1: string} flash type + message */
    private function bulkRun(Collection $rules): array
    {
        $rules->each(fn (DebugRule $rule) => RunDebugRuleJob::dispatch($rule->id));

        $count = $rules->count();

        return ['success', config('queue.default') === 'sync'
            ? "Ran {$count} rule(s). Check each rule's logs for the results."
            : "Queued {$count} rule(s) to run in the background. Check Last Run and the rule logs for results."];
    }

    /** @return array{0: string, 1: string} */
    private function bulkExport(Collection $rules, RuleSyncService $service): array
    {
        // Rules whose file already exists are skipped, matching the hidden
        // Export button on those rows.
        $fileRuleIds = $service->fileRuleIds();
        $toExport = $rules->reject(fn (DebugRule $rule) => in_array($rule->id, $fileRuleIds));

        if ($toExport->isEmpty()) {
            return ['error', 'Nothing exported: the selected rules are already linked to files.'];
        }

        $files = $toExport->map(fn (DebugRule $rule) => $service->export($rule)['file']);

        return ['success', "Exported {$files->count()} rule(s) to files: {$files->implode(', ')}."
            .$this->skippedNote($rules->count() - $toExport->count(), 'already linked to a file')];
    }

    /** @return array{0: string, 1: string} */
    private function bulkStatus(Collection $rules, string $status, RuleSyncService $service): array
    {
        $changed = $rules->reject(fn (DebugRule $rule) => $rule->status === $status);

        $changed->each(function (DebugRule $rule) use ($status, $service) {
            $rule->update(['status' => $status]);
            // Status is part of the rule file, so keep a linked file in step.
            $service->writeLinkedFile($rule);
        });

        $verb = $status === 'active' ? 'Activated' : 'Deactivated';

        return ['success', "{$verb} {$changed->count()} rule(s)."
            .$this->skippedNote($rules->count() - $changed->count(), "already {$status}")];
    }

    /** @return array{0: string, 1: string} */
    private function bulkSuppress(Collection $rules, bool $suppress): array
    {
        $changed = $rules->reject(fn (DebugRule $rule) => $rule->suppress === $suppress);

        $changed->each(fn (DebugRule $rule) => $rule->update([
            'suppress' => $suppress,
            'suppress_notes' => $suppress ? 'Suppressed Manually' : null,
        ]));

        $verb = $suppress ? 'Suppressed' : 'Unsuppressed';

        return ['success', "{$verb} {$changed->count()} rule(s)."
            .$this->skippedNote($rules->count() - $changed->count(), 'already '.strtolower($verb))];
    }

    /** @return array{0: string, 1: string} */
    private function bulkDelete(Collection $rules, RuleSyncService $service): array
    {
        // Remove each rule's linked file + sync record, then the rule.
        $rules->each(function (DebugRule $rule) use ($service) {
            $service->deleteLinkedFile($rule);
            $rule->delete();
        });

        return ['success', "Deleted {$rules->count()} rule(s)."];
    }

    private function skippedNote(int $skipped, string $reason): string
    {
        return $skipped > 0 ? " Skipped {$skipped} {$reason}." : '';
    }

    public function edit($id)
    {
        $rule = DebugRule::findOrFail($id);
        $indexUrl = $this->indexUrl();

        return view('debug-monitor::rules.form', compact('rule', 'indexUrl'));
    }

    public function update(Request $request, $id, RuleSyncService $service)
    {
        $validated = $this->validateRule($request);

        $rule = DebugRule::findOrFail($id);
        $rule->update($this->normalizeValidatedRule($validated));

        // Keep the linked file (if any) in step with the edited rule.
        $file = $service->writeLinkedFile($rule);

        $message = $file
            ? "Rule updated successfully! Linked file {$file} was rewritten."
            : 'Rule updated successfully!';

        return $this->redirectToIndex()->with('success', $message);
    }

    public function show(Request $request, $id)
    {
        $rule = DebugRule::findOrFail($id);

        $logs = $rule->logs()
            ->latest()
            ->orderByDesc('id') // stable order for logs written in the same second
            ->paginate(20);

        // Deleting the last log on the last page would leave an empty page.
        if ($logs->isEmpty() && $logs->currentPage() > 1) {
            $request->session()->reflash(); // keep "Log deleted successfully!"

            return redirect()->route('debug-monitor.rules.show', ['rule' => $rule->id, 'page' => $logs->lastPage()]);
        }

        $indexUrl = $this->indexUrl();

        return view('debug-monitor::rules.show', compact('rule', 'logs', 'indexUrl'));
    }

    public function destroy($id, RuleSyncService $service)
    {
        $rule = DebugRule::findOrFail($id);

        // Remove the linked file + sync record first, then the rule itself.
        $service->deleteLinkedFile($rule);
        $rule->delete();

        return $this->redirectToIndex()->with('success', 'Rule deleted successfully!');
    }

    /**
     * A log's full result as JSON. The rule page only renders a short preview
     * and loads this on demand, since a result can hold up to max_rows rows.
     */
    public function showLog($id)
    {
        return response()->json(DebugRuleLog::findOrFail($id)->result);
    }

    public function destroyLog($id)
    {
        $log = DebugRuleLog::findOrFail($id);
        $log->delete();

        return back()->with('success', 'Log deleted successfully!');
    }

    public function suppress(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:suppress,unsuppress',
            'suppress_notes' => 'nullable|string|max:500',
        ]);

        $rule = DebugRule::findOrFail($id);

        $rule->update([
            'suppress' => $validated['action'] === 'suppress',
            'suppress_notes' => $validated['action'] === 'suppress' ? ($validated['suppress_notes'] ?? 'Suppressed Manually') : null,
        ]);

        return back()->with('success', 'Rule suppression status updated.');
    }

    /**
     * The rules list URL with the page and filters the user last viewed.
     */
    private function indexUrl(bool $keepPage = true): string
    {
        $query = session(self::INDEX_QUERY_KEY, []);

        if (! $keepPage) {
            unset($query['page']);
        }

        return route('debug-monitor.rules.index', $query);
    }

    private function redirectToIndex(bool $keepPage = true): RedirectResponse
    {
        return redirect()->to($this->indexUrl($keepPage));
    }

    private function validateRule(Request $request): array
    {
        return $request->validate(DebugRule::validationRules());
    }

    private function normalizeValidatedRule(array $validated): array
    {
        if (array_key_exists('expected_json', $validated) && is_string($validated['expected_json'])) {
            $decoded = json_decode($validated['expected_json'], true);

            $validated['expected_json'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return $validated;
    }

    public function dashboard()
    {
        $totalRules = DebugRule::count();
        $activeRules = DebugRule::where('status', 'active')->count();
        $inactiveRules = DebugRule::where('status', 'inactive')->count();
        $suppressRules = DebugRule::where('suppress', true)->count();

        // One row per rule that failed in the last 24 hours (its latest failure
        // plus a count), so a rule failing every minute can't flood the page.
        $recentErrors = DebugRuleLog::query()
            ->selectRaw('debug_rule_id, max(id) as latest_log_id, max(created_at) as last_failed_at, count(*) as failures')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('debug_rule_id')
            ->orderByDesc('last_failed_at')
            ->orderByDesc('latest_log_id')
            ->paginate(10);

        $latestLogs = DebugRuleLog::with('rule')
            ->whereIn('id', $recentErrors->pluck('latest_log_id'))
            ->get()
            ->keyBy('id');

        // A log deleted between the two queries simply drops out of the page.
        $recentErrors->setCollection($recentErrors->getCollection()
            ->map(function (DebugRuleLog $group) use ($latestLogs) {
                $log = $latestLogs->get($group->latest_log_id);
                $log?->setAttribute('failures', (int) $group->failures);

                return $log;
            })
            ->filter()
            ->values());

        return view('debug-monitor::dashboard', compact(
            'totalRules',
            'activeRules',
            'inactiveRules',
            'suppressRules',
            'recentErrors'
        ));
    }
}
