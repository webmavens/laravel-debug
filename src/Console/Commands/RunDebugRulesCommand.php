<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleRunner;

class RunDebugRulesCommand extends Command
{
    protected $signature = 'debug-monitor:run';
    protected $description = 'Run all active debug rules and log any anomalies';

    public function handle(RuleRunner $runner)
    {
        $this->info('Running debug rules...');
        $now = Carbon::now();

        $rules = DebugRule::where('status', 'active')
                ->get()
                ->filter(function ($rule) use ($now) {
                    if (!$rule->last_run_at) {
                        return true;
                    }

                    $lastRun = Carbon::parse($rule->last_run_at);
                    $diff = $lastRun->diffInMinutes($now);

                    return $diff >= $rule->frequency_minutes;
                });

        if ($rules->isEmpty()) {
            $this->info('No rules due for execution.');
            return Command::SUCCESS;
        }

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

        if ($rule->suppress) {
            $this->warn("⚠️ Rule failed but suppressed: {$rule->name}");
        } elseif ($rule->notification_level === 'none') {
            $this->warn("⚠️ Rule failed (no notification): {$rule->name}");
        } else {
            $this->warn("⚠️ Rule failed: {$rule->name}");
        }
    }
}
