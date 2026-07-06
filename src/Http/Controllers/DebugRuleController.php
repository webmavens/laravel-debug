<?php

namespace Webmavens\DebugMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Models\DebugRuleSync;
use Webmavens\DebugMonitor\Services\RuleRunner;
use Webmavens\DebugMonitor\Services\RuleSyncService;

class DebugRuleController extends Controller
{
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
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Only rules whose linked file is still on disk count as "file" rules, so a
        // manually deleted file immediately drops the badge and re-enables Export.
        $existingFiles = collect($service->files())->map(fn ($file) => basename($file))->all();

        $fileRuleIds = DebugRuleSync::whereNotNull('debug_rule_id')
            ->whereIn('rule_file', $existingFiles)
            ->pluck('debug_rule_id')
            ->all();

        $pendingCount = $service->pendingCount();

        return view('debug-monitor::rules.index', compact('rules', 'fileRuleIds', 'pendingCount'));
    }

    public function create()
    {
        return view('debug-monitor::rules.form');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        DebugRule::create($this->normalizeValidatedRule($validated));

        return redirect()
            ->route('debug-monitor.rules.index')
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

        return redirect()
            ->route('debug-monitor.rules.index')
            ->with($status, $message);
    }

    public function export($id, RuleSyncService $service)
    {
        $rule = DebugRule::findOrFail($id);

        $result = $service->export($rule);

        $verb = $result['created'] ? 'exported to' : 'updated';

        return redirect()
            ->route('debug-monitor.rules.index')
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

    public function edit($id)
    {
        $rule = DebugRule::findOrFail($id);

        return view('debug-monitor::rules.form', compact('rule'));
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

        return redirect()
            ->route('debug-monitor.rules.index')
            ->with('success', $message);
    }

    public function show($id)
    {
        $rule = DebugRule::findOrFail($id);

        $logs = $rule->logs()
            ->latest()
            ->limit(50)
            ->get();

        return view('debug-monitor::rules.show', compact('rule', 'logs'));
    }

    public function destroy($id, RuleSyncService $service)
    {
        $rule = DebugRule::findOrFail($id);

        // Remove the linked file + sync record first, then the rule itself.
        $service->deleteLinkedFile($rule);
        $rule->delete();

        return redirect()
            ->route('debug-monitor.rules.index')
            ->with('success', 'Rule deleted successfully!');
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

        $recentErrors = DebugRuleLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->get();

        return view('debug-monitor::dashboard', compact(
            'totalRules',
            'activeRules',
            'inactiveRules',
            'suppressRules',
            'recentErrors'
        ));
    }
}
