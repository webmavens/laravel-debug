<?php

namespace Webmavens\DebugMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Carbon\Carbon;

class DebugRuleController extends Controller
{
    public function index(Request $request)
    {
        $level = $request->importance_level;
        $allowedLevels = ['high', 'medium', 'low'];

        $rules = DebugRule::when(in_array($level, $allowedLevels), function ($query) use ($level) {
            $query->where('importance_level', $level);
        })
        ->latest()
        ->paginate(10);

        return view('debug-monitor::rules.index', compact('rules'));
    }

    public function create()
    {
        return view('debug-monitor::rules.form');
    }

    public function store(Request $request)
    {
        $validated = $this->validateRule($request);

        DebugRule::create($validated);

        return redirect()
            ->route('debug-monitor.rules.index')
            ->with('success', 'Rule added successfully!');
    }

    public function edit($id)
    {
        $rule = DebugRule::findOrFail($id);

        return view('debug-monitor::rules.form', compact('rule'));
    }

    public function update(Request $request, $id)
    {
        $validated = $this->validateRule($request);

        $rule = DebugRule::findOrFail($id);
        $rule->update($validated);

        return redirect()
            ->route('debug-monitor.rules.index')
            ->with('success', 'Rule updated successfully!');
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

    public function destroy($id)
    {
        $rule = DebugRule::findOrFail($id);
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
        return $request->validate([
            'name' => 'required|string|max:255',
            'sql_query' => 'required|string',
            'frequency_minutes' => 'required|integer|min:1',
            'importance_level' => 'required|in:high,medium,low',
            'expected_rows_operator' => 'required|in:=,>,<,>=,<=',
            'expected_rows' => 'nullable|integer|min:0',
            'expected_json' => 'nullable|json',
            'notification_level' => 'required|in:default,urgent',
            'status' => 'required|in:active,inactive',
        ]);
    }

    public function dashboard()
    {
        $now = Carbon::now();
        $totalRules = DebugRule::count();
        $activeRules = DebugRule::where('status', 'active')->count();
        $inactiveRules = DebugRule::where('status', 'inactive')->count();
        $suppressRules = DebugRule::where('suppress', true)->count();

        $recentLogs = DebugRuleLog::latest()->take(10)->get();
        $rules = DebugRule::orderBy('id', 'desc')->get();
        $recentErrors = DebugRuleLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->get();

        $runsNext1 = [];
        $runsNext5 = [];
        $runsNext15 = [];
        $highFrequency = [];

        foreach ($rules as $rule) {
            if (empty($rule->frequency_minutes) || $rule->status !== 'active') {
                continue;
            }

            // High Frequency Rules (1–3 mins)
            if ($rule->frequency_minutes >= 1 && $rule->frequency_minutes <= 3) {
                $highFrequency[] = $rule;
            }

            // Ensure lastRun is parsed in app timezone (avoid timezone issues)
            if ($rule->last_run_at) {
                $lastRun = Carbon::parse($rule->last_run_at, config('app.timezone'));
            } else {
                $lastRun = $now->copy()->subMinutes($rule->frequency_minutes);
            }

            // Next run timestamp
            $periodSeconds = $rule->frequency_minutes * 60;
            $elapsedSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

            // If elapsed is negative (lastRun in future), clamp it
            if ($elapsedSeconds < 0) {
                $elapsedSeconds = 0;
            }

            // how many full periods have passed since lastRun (at least 1)
            $cycles = (int) ceil( max($elapsedSeconds, 1) / $periodSeconds );

            // compute the next scheduled occurrence
            $nextRun = $lastRun->copy()->addSeconds($cycles * $periodSeconds);

            // now difference in seconds (nextRun - now), will be >= 0 normally
            $diffSeconds = $nextRun->getTimestamp() - $now->getTimestamp();

            // If nextRun is still in the past (edge cases), skip
            if ($diffSeconds < -59 || $diffSeconds > 15 * 60) {
                continue;
            }

            // Mutually exclusive buckets based on seconds until next run
            if ($diffSeconds <= 60) {
                $runsNext1[] = ['rule' => $rule, 'nextRun' => $nextRun, 'diffSeconds' => $diffSeconds];
            } elseif ($diffSeconds <= 5 * 60) {
                $runsNext5[] = ['rule' => $rule, 'nextRun' => $nextRun, 'diffSeconds' => $diffSeconds];
            } elseif ($diffSeconds <= 15 * 60) {
                $runsNext15[] = ['rule' => $rule, 'nextRun' => $nextRun, 'diffSeconds' => $diffSeconds];
            }
        }

        return view('debug-monitor::dashboard', compact(
            'totalRules',
            'activeRules',
            'inactiveRules',
            'suppressRules',
            'recentLogs',
            'recentErrors',
            'rules',
            'runsNext1',
            'runsNext5',
            'runsNext15',
            'highFrequency'
        ));
    }
}
