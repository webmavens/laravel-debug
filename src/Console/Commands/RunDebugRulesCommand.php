<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Webmavens\DebugMonitor\Jobs\RunScheduledDebugRuleJob;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleRunner;

class RunDebugRulesCommand extends Command
{
    protected $signature = 'debug-monitor:run
        {--sync : Run due rules one after another in this process instead of queueing them}';

    protected $description = 'Run all active debug rules that are due and log any anomalies';

    public function handle(RuleRunner $runner)
    {
        $now = Carbon::now();

        // Timed suppressions that have ended stop showing as suppressed.
        DebugRule::releaseExpiredSuppressions();

        $rules = DebugRule::where('status', 'active')
            ->get()
            ->filter(fn (DebugRule $rule) => $rule->isDue($now));

        if ($rules->isEmpty()) {
            $this->info('No rules due for execution.');

            return Command::SUCCESS;
        }

        if ($this->option('sync') || ! config('debug-monitor.queue_runs', true)) {
            return $this->runInline($rules, $runner);
        }

        // One job per rule, so a slow query only delays its own rule.
        $rules->each(fn (DebugRule $rule) => RunScheduledDebugRuleJob::dispatch($rule->id));

        $this->info("Queued {$rules->count()} due rule(s).");

        return Command::SUCCESS;
    }

    private function runInline($rules, RuleRunner $runner): int
    {
        $this->info('Running debug rules...');

        foreach ($rules as $rule) {
            $this->line("Checking rule: {$rule->name}");

            $outcome = $runner->run($rule);

            $this->report($rule, $outcome);
        }

        $this->info('✅ Debug rules executed successfully.');

        return Command::SUCCESS;
    }

    /**
     * Echo a per-rule result line based on the runner's outcome.
     */
    protected function report(DebugRule $rule, array $outcome): void
    {
        if ($outcome['exception'] !== null) {
            $this->error("Error executing rule: {$rule->name}");
            return;
        }

        if ($outcome['status'] === 'ok') {
            $this->info("✅ Rule passed: {$rule->name}");
            return;
        }

        if ($rule->isSuppressed()) {
            $this->warn("⚠️ Rule failed but suppressed: {$rule->name}");
        } elseif ($rule->notification_level === 'none') {
            $this->warn("⚠️ Rule failed (no notification): {$rule->name}");
        } else {
            $this->warn("⚠️ Rule failed: {$rule->name}");
        }
    }
}
