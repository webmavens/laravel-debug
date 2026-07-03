<?php

namespace Webmavens\DebugMonitor\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;

/**
 * Executes a single debug rule: runs its query, evaluates the expectations,
 * writes a log, updates the rule's last-run state, and (optionally) notifies.
 *
 * Shared by the scheduled runner (debug-monitor:run) and the "Run now" UI
 * action so both behave identically.
 */
class RuleRunner
{
    /**
     * Run one rule.
     *
     * @param  bool  $notify  Send failure notifications (scheduled runs) or stay
     *                        silent (manual "Run now" from the UI).
     * @return array{status: string, exception: ?string, errorLog: array, notified: bool}
     */
    public function run(DebugRule $rule, bool $notify = true): array
    {
        $now = Carbon::now();

        try {
            $result = DB::select($rule->sql_query);
            $actual = count($result);

            $operator = $rule->expected_rows_operator ?? '=';
            $expected = $rule->expected_rows ?? 0;
            $expectedJson = $this->normalizeExpectedJson($rule->expected_json);

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

            $notified = false;

            if (!$isValid) {
                if ($rule->suppress) {
                    Log::info("Rule failed but suppressed: {$rule->name}", $errorLog);
                } elseif ($rule->notification_level === 'none') {
                    Log::warning("Rule failed (no notification): {$rule->name}", $errorLog);
                } else {
                    Log::warning("Rule failed: {$rule->name}", $errorLog);

                    if ($notify) {
                        Notification::route('mail', config('debug-monitor.notify_email'))
                            ->notify(new DebugRuleAlert($rule, $errorLog));
                        $notified = true;
                    }
                }
            }

            return [
                'status' => $isValid ? 'ok' : 'failed',
                'exception' => null,
                'errorLog' => $errorLog,
                'notified' => $notified,
            ];
        } catch (\Throwable $e) {
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

            return [
                'status' => 'failed',
                'exception' => $e->getMessage(),
                'errorLog' => ['exception' => $e->getMessage()],
                'notified' => false,
            ];
        }
    }

    protected function normalizeExpectedJson(mixed $expectedJson): ?array
    {
        if (empty($expectedJson)) {
            return null;
        }

        if (is_array($expectedJson)) {
            return $expectedJson;
        }

        if (is_string($expectedJson)) {
            $decoded = json_decode($expectedJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
