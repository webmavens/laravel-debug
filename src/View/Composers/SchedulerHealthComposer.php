<?php

namespace Webmavens\DebugMonitor\View\Composers;

use Carbon\Carbon;
use Illuminate\View\View;
use Webmavens\DebugMonitor\Models\DebugRule;

/**
 * Warns on every page when active rules are overdue, which usually means the
 * scheduler or the queue worker isn't running: otherwise rules just stop
 * running and nothing says so.
 */
class SchedulerHealthComposer
{
    /** Minutes past a rule's schedule before it counts as overdue. */
    private const GRACE_MINUTES = 10;

    public function compose(View $view): void
    {
        $now = Carbon::now();

        $overdue = DebugRule::where('status', 'active')
            ->get(['id', 'status', 'frequency_minutes', 'last_run_at', 'created_at'])
            ->filter(fn (DebugRule $rule) => $rule->isOverdue($now, self::GRACE_MINUTES))
            ->count();

        $view->with([
            'overdueRuleCount' => $overdue,
            // Scheduled rules are queued jobs unless they run inline (config or sync queue).
            'needsQueueWorker' => config('debug-monitor.queue_runs', true) && config('queue.default') !== 'sync',
        ]);
    }
}
