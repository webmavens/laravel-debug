<?php

namespace Webmavens\DebugMonitor\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Webmavens\DebugMonitor\Models\DebugRule;

/**
 * Runs a due rule for the scheduler (debug-monitor:run), with alerts.
 *
 * Unique per rule: while one is queued or running, the every-minute scheduler
 * won't queue another for the same rule, so a slow worker can't pile up
 * duplicates. The lock expires after $uniqueFor seconds in case a worker dies
 * mid-job.
 */
class RunScheduledDebugRuleJob extends RunDebugRuleJob implements ShouldBeUnique
{
    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return (string) $this->ruleId;
    }

    /**
     * Re-check when the job actually runs: the rule may have been deactivated,
     * or already run, since it was queued.
     */
    protected function shouldRun(DebugRule $rule): bool
    {
        return $rule->status === 'active' && $rule->isDue();
    }

    protected function notifies(): bool
    {
        return true;
    }
}
