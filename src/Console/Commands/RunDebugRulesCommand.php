<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;

class RunDebugRulesCommand extends Command
{
    protected $signature = 'debug-monitor:run';
    protected $description = 'Run all active debug rules and log any anomalies';

    public function handle()
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
            $this->runRule($rule);
        }

        $this->info('✅ Debug rules executed successfully.');

        return Command::SUCCESS;
    }

    protected function runRule(DebugRule $rule)
    {
        $this->line("Checking rule: {$rule->name}");
        $now = Carbon::now();

        try {
            $result = DB::select($rule->sql_query);
            $actual = count($result);

            $operator = $rule->expected_rows_operator ?? '=';
            $expected = $rule->expected_rows ?? 0;
            $expectedJson = $rule->expected_json ?: null;

            $isValid = true;
            $errorLog = [];

            $comparison = match ($operator) {
                '=' => $actual == $expected,
                '>' => $actual > $expected,
                '<' => $actual < $expected,
                '>=' => $actual >= $expected,
                '<=' => $actual <= $expected,
                default => true,
            };

            // Compare row count
            if (! $comparison) {
                $isValid = false;
                $errorLog['row_mismatch'] = [
                    'expected' => "{$operator} {$expected}",
                    'actual' => $actual,
                    'query' => $rule->sql_query,
                    'result' => $result,
                ];
            }

            // Compare expected JSON (if any)
            if ($expectedJson && !empty($result)) {
                $resultArray = json_decode(json_encode($result), true);

                $matched = false;

                foreach ($resultArray as $index => $row) {
                    $allMatch = true;

                    foreach ($expectedJson as $key => $expectedValue) {
                        $actualValue = $row[$key] ?? null;

                        if ($actualValue != $expectedValue) {
                            $allMatch = false;
                            $errorLog['data_mismatch'][$index][$key] = [
                                'expected' => $expectedValue,
                                'actual' => $actualValue,
                            ];
                        }
                    }

                    if ($allMatch) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    $isValid = false;
                    $errorLog['message'] = 'No rows matched the expected JSON pattern.';
                }
            }

            // Save the execution result in logs
            DebugRuleLog::create([
                'debug_rule_id' => $rule->id,
                'result' => $isValid ? $result : $errorLog,
                'status' => $isValid ? 'ok' : 'failed',
            ]);

            // Update last run
            $rule->last_run_at = $now;
            $rule->last_debug_log = $isValid ? $result : $errorLog;
            $rule->save();

            if (!$isValid) {
                if ($rule->suppress) {
                    $this->warn("⚠️ Rule failed but suppressed: {$rule->name}");
                    Log::info("Rule failed but suppressed: {$rule->name}", $errorLog);
                } elseif ($rule->notification_level === 'none') {
                    $this->warn("⚠️ Rule failed (no notification): {$rule->name}");
                    Log::warning("Rule failed (no notification): {$rule->name}", $errorLog);
                } else {
                    $this->warn("⚠️ Rule failed: {$rule->name}");
                    Log::warning("Rule failed: {$rule->name}", $errorLog);

                    Notification::route('mail', config('debug-monitor.notify_email'))
                    ->notify(new DebugRuleAlert($rule, $errorLog));
                }
            } else {
                $this->info("✅ Rule passed: {$rule->name}");
            }
        } catch (\Throwable $e) {
            $this->error("Error executing rule: {$rule->name}");
            Log::error("Exception in rule: {$rule->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DebugRuleLog::create([
                'debug_rule_id' => $rule->id,
                'result' => ['exception' => $e->getMessage()],
                'status' => 'failed',
            ]);

            $rule->update([
                'last_debug_log' => [
                    'exception' => $e->getMessage(),
                ],
                'last_run_at' => $now,
            ]);
        }
    }
}
