<?php

namespace Webmavens\DebugMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleRunner;

/**
 * Runs a single rule on the queue for a manual run (the bulk "Run" action),
 * so a large selection doesn't block the web request. Manual runs are silent.
 */
class RunDebugRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * A failed rule is recorded in its logs, not thrown, so retrying would
     * only re-run a potentially heavy query.
     */
    public int $tries = 1;

    public function __construct(public int $ruleId)
    {
    }

    public function handle(RuleRunner $runner): void
    {
        // The rule may have been deleted between queueing and running.
        $rule = DebugRule::find($this->ruleId);

        if (! $rule || ! $this->shouldRun($rule)) {
            return;
        }

        $runner->run($rule, notify: $this->notifies());
    }

    protected function shouldRun(DebugRule $rule): bool
    {
        return true;
    }

    /** Manual runs never send alerts, same as "Run now". */
    protected function notifies(): bool
    {
        return false;
    }
}
