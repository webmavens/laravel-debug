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

    private const BULK_ACTIONS = ['run', 'export', 'activate', 'deactivate', 'suppress', 'unsuppress', 'group', 'delete'];

    /** How long a rule can be suppressed for, in hours; "forever" lasts until unsuppressed. */
    private const SUPPRESS_DURATIONS = ['1', '24', '168', 'forever'];

    /** Sortable list columns (query value => column) and the direction each starts in. */
    private const SORTS = [
        'name' => ['column' => 'name', 'direction' => 'asc'],
        'status' => ['column' => 'status', 'direction' => 'asc'],
        'last_run' => ['column' => 'last_run_at', 'direction' => 'desc'],
    ];

    /** Group filter value for rules without a group. */
    private const NO_GROUP = '__none__';

    /** "Rows per page" choices on the rules list; anything else falls back to the default. */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    private const DEFAULT_PER_PAGE = 25;

    public function index(Request $request, RuleSyncService $service)
    {
        $level = $request->importance_level;
        $allowedLevels = ['high', 'medium', 'low'];

        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status');
        $group = $request->get('group');

        $sort = array_key_exists($request->get('sort'), self::SORTS) ? $request->get('sort') : null;
        $direction = null;
        if ($sort) {
            $direction = in_array($request->get('direction'), ['asc', 'desc'], true)
                ? $request->get('direction')
                : self::SORTS[$sort]['direction'];
        }

        $perPage = (int) $request->get('per_page', self::DEFAULT_PER_PAGE);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        $rules = DebugRule::query()
            ->when($search !== '', fn ($query) => $this->search($query, $search))
            ->when($group === self::NO_GROUP, fn ($query) => $query->whereNull('group_name'))
            ->when(is_string($group) && $group !== '' && $group !== self::NO_GROUP, fn ($query) => $query->where('group_name', $group))
            ->when(in_array($level, $allowedLevels), fn ($query) => $query->where('importance_level', $level))
            ->when($status === 'active', fn ($query) => $query->where('status', 'active'))
            ->when($status === 'inactive', fn ($query) => $query->where('status', 'inactive'))
            ->when($status === 'suppressed', fn ($query) => $query->suppressed())
            // Active rules whose latest run failed (a deactivated rule is paused, not failing).
            ->when($status === 'failing', fn ($query) => $query->where('status', 'active')->where('last_status', 'failed'))
            // Never-run rules go last whichever way Last Run is sorted.
            ->when($sort === 'last_run', fn ($query) => $query->orderByRaw('last_run_at is null'))
            ->when($sort, fn ($query) => $query->orderBy(self::SORTS[$sort]['column'], $direction))
            // Newest first by default, and within equal sort values. id breaks
            // created_at ties (rules synced from files share a second); without it
            // MySQL may shuffle tied rows between pages.
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
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

        $perPageOptions = self::PER_PAGE_OPTIONS;
        $groups = $this->groups();
        $noGroup = self::NO_GROUP;
        $sortLinks = $this->sortLinks($request, $sort, $direction);

        return view('debug-monitor::rules.index', compact(
            'rules', 'fileRuleIds', 'pendingCount', 'perPage', 'perPageOptions',
            'groups', 'noGroup', 'sort', 'direction', 'sortLinks'
        ));
    }

    /**
     * Match the term anywhere in the name, group or SQL. % and _ in the term
     * are matched literally.
     */
    private function search($query, string $search)
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        return $query->where(function ($query) use ($like) {
            foreach (['name', 'group_name', 'sql_query'] as $column) {
                $query->orWhereRaw($query->getGrammar()->wrap($column)." like ? escape '!'", [$like]);
            }
        });
    }

    /** Existing group names, alphabetically. */
    private function groups(): array
    {
        return DebugRule::whereNotNull('group_name')->distinct()->orderBy('group_name')->pluck('group_name')->all();
    }

    /**
     * Header link for each sortable column: the current filters, page 1, and
     * the column's sort (toggled if it is already the active one).
     *
     * @return array<string, string>
     */
    private function sortLinks(Request $request, ?string $sort, ?string $direction): array
    {
        $query = $request->except(['page', 'sort', 'direction']);

        return collect(self::SORTS)->mapWithKeys(function (array $config, string $key) use ($query, $sort, $direction) {
            $next = $key === $sort ? ($direction === 'asc' ? 'desc' : 'asc') : $config['direction'];

            return [$key => route('debug-monitor.rules.index', $query + ['sort' => $key, 'direction' => $next])];
        })->all();
    }

    /** Run a query from the rule form without saving it (the "Test query" button). */
    public function test(Request $request, RuleRunner $runner)
    {
        $validated = $request->validate(array_intersect_key(DebugRule::validationRules(), array_flip([
            'sql_query', 'expected_rows', 'expected_json',
        ])) + ['expected_rows_operator' => 'nullable|in:=,>,<,>=,<=']);

        return response()->json($runner->test($validated));
    }

    public function create()
    {
        $indexUrl = $this->indexUrl();
        $groups = $this->groups();

        return view('debug-monitor::rules.form', compact('indexUrl', 'groups'));
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
            'suppress' => $this->bulkSuppress($rules, true, $request->input('duration', 'forever')),
            'unsuppress' => $this->bulkSuppress($rules, false),
            'group' => $this->bulkGroup($rules, $request->input('group_name'), $service),
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
    private function bulkSuppress(Collection $rules, bool $suppress, mixed $duration = null): array
    {
        if ($suppress && ! in_array($duration, self::SUPPRESS_DURATIONS, true)) {
            return ['error', 'Choose how long to suppress the rules for.'];
        }

        // A suppression that has ended counts as not suppressed.
        $changed = $rules->reject(fn (DebugRule $rule) => $rule->isSuppressed() === $suppress);

        $changed->each(fn (DebugRule $rule) => $rule->update($suppress
            ? $this->suppression($duration, 'Suppressed Manually')
            : $this->suppression(null)));

        $verb = $suppress ? 'Suppressed' : 'Unsuppressed';

        return ['success', "{$verb} {$changed->count()} rule(s)."
            .$this->skippedNote($rules->count() - $changed->count(), 'already '.strtolower($verb))];
    }

    /** @return array{0: string, 1: string} */
    private function bulkGroup(Collection $rules, mixed $group, RuleSyncService $service): array
    {
        $group = $this->normalizeGroup($group);

        if ($group !== null && mb_strlen($group) > 100) {
            return ['error', 'A group name can be at most 100 characters.'];
        }

        $rules->each(function (DebugRule $rule) use ($group, $service) {
            $rule->update(['group_name' => $group]);
            // The group is part of the rule file, so keep a linked file in step.
            $service->writeLinkedFile($rule);
        });

        return ['success', $group === null
            ? "Removed {$rules->count()} rule(s) from their group."
            : "Moved {$rules->count()} rule(s) to group \"{$group}\"."];
    }

    /** Trim a group name; blank means no group. */
    private function normalizeGroup(mixed $group): ?string
    {
        $group = trim((string) $group);

        return $group === '' ? null : $group;
    }

    /**
     * Attributes for suppressing a rule for $duration (hours or "forever"),
     * or for unsuppressing it when $duration is null.
     */
    private function suppression(?string $duration, ?string $notes = null): array
    {
        if ($duration === null) {
            return ['suppress' => false, 'suppress_notes' => null, 'suppressed_until' => null];
        }

        return [
            'suppress' => true,
            'suppress_notes' => $notes,
            'suppressed_until' => $duration === 'forever' ? null : now()->addHours((int) $duration),
        ];
    }

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
        $groups = $this->groups();

        return view('debug-monitor::rules.form', compact('rule', 'indexUrl', 'groups'));
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
            'duration' => 'nullable|in:'.implode(',', self::SUPPRESS_DURATIONS),
        ]);

        $rule = DebugRule::findOrFail($id);

        $rule->update($validated['action'] === 'suppress'
            ? $this->suppression($validated['duration'] ?? 'forever', $validated['suppress_notes'] ?? 'Suppressed Manually')
            : $this->suppression(null));

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
        $validated['group_name'] = $this->normalizeGroup($validated['group_name'] ?? null);

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
        $suppressRules = DebugRule::suppressed()->count();

        // Active rules whose latest run took longest.
        $slowestRules = DebugRule::where('status', 'active')
            ->whereNotNull('last_duration_ms')
            ->orderByDesc('last_duration_ms')
            ->orderBy('id')
            ->limit(5)
            ->get();

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
            'slowestRules',
            'recentErrors'
        ));
    }
}
