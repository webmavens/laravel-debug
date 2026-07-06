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
 *
 * Reliability notes for complex/large queries:
 *  - The query is streamed with DB::cursor() so the full result set is never
 *    loaded into memory just to be counted; only a bounded sample (max_rows)
 *    is retained for logging and JSON matching.
 *  - Queries are held to a per-statement timeout (query_timeout) where the
 *    driver supports it, so a runaway query aborts instead of hanging the run.
 *  - Only read-only (SELECT/WITH/SHOW/EXPLAIN), single-statement queries are
 *    allowed, so a rule can never mutate data.
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
            $sql = $this->normalizeQuery($rule->sql_query);
            $this->assertReadOnly($sql);

            $operator = $rule->expected_rows_operator ?? '=';
            $expected = $rule->expected_rows ?? 0;
            $expectedJson = $this->normalizeExpectedJson($rule->expected_json);
            $maxRows = max(1, (int) config('debug-monitor.max_rows', 1000));

            $this->applyTimeout();

            try {
                $data = $this->collectResult($sql, $expectedJson, $maxRows);
            } finally {
                $this->clearTimeout();
            }

            $actual = $data['actual'];

            $isValid = true;
            $errorLog = [];

            $comparison = match ($operator) {
                '=' => $actual == $expected,
                '>' => $actual > $expected,
                '<' => $actual < $expected,
                '>=' => $actual >= $expected,
                '<=' => $actual <= $expected,
                default => false,
            };

            // Compare row count
            if (! $comparison) {
                $isValid = false;
                $errorLog['row_mismatch'] = [
                    'expected' => "{$operator} {$expected}",
                    'actual' => $actual,
                    'query' => $sql,
                    'result' => $data['sample'],
                ];

                if ($data['truncated']) {
                    $errorLog['row_mismatch']['truncated'] =
                        "showing first {$maxRows} of {$actual} rows";
                }
            }

            // Compare expected JSON (if any). Matching runs over every streamed
            // row, so the result is accurate even when only a sample is stored.
            if ($expectedJson !== null && $actual > 0 && $data['matched'] === false) {
                $isValid = false;
                $errorLog['message'] = 'No rows matched the expected JSON pattern.';

                if (! empty($data['mismatches'])) {
                    $errorLog['data_mismatch'] = $data['mismatches'];
                }
            }

            // Save the execution result in logs (capped sample only).
            DebugRuleLog::create([
                'debug_rule_id' => $rule->id,
                'result' => $isValid ? $data['sample'] : $errorLog,
                'status' => $isValid ? 'ok' : 'failed',
            ]);

            // Update last run
            $rule->last_run_at = $now;
            $rule->last_debug_log = $isValid ? $data['sample'] : $errorLog;
            $rule->save();

            $notified = false;

            if (! $isValid) {
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

    /**
     * Stream the query, counting every row while retaining only a bounded
     * sample. The expected-JSON pattern is evaluated against every row so the
     * match verdict stays accurate even though storage is capped.
     *
     * @return array{actual: int, sample: array, truncated: bool, matched: ?bool, mismatches: array}
     */
    protected function collectResult(string $sql, ?array $expectedJson, int $maxRows): array
    {
        $actual = 0;
        $sample = [];
        $matched = $expectedJson === null ? null : false;
        $mismatches = [];

        foreach (DB::cursor($sql) as $row) {
            $rowArray = (array) $row;
            $actual++;

            if (count($sample) < $maxRows) {
                $sample[] = $rowArray;
            }

            if ($expectedJson !== null && $matched === false) {
                $allMatch = true;

                foreach ($expectedJson as $key => $expectedValue) {
                    $actualValue = $rowArray[$key] ?? null;

                    if ($actualValue != $expectedValue) {
                        $allMatch = false;

                        if (count($mismatches) < $maxRows) {
                            $mismatches[$actual - 1][$key] = [
                                'expected' => $expectedValue,
                                'actual' => $actualValue,
                            ];
                        }
                    }
                }

                if ($allMatch) {
                    $matched = true;
                }
            }
        }

        return [
            'actual' => $actual,
            'sample' => $sample,
            'truncated' => $actual > count($sample),
            'matched' => $matched,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * Trim whitespace and a single trailing semicolon from the query so it can
     * be executed as one statement.
     */
    protected function normalizeQuery(mixed $sql): string
    {
        $sql = trim((string) $sql);

        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }

        return $sql;
    }

    /**
     * Guard against anything that could mutate data or stack extra statements.
     * Can be disabled via config('debug-monitor.read_only').
     */
    protected function assertReadOnly(string $sql): void
    {
        if (! config('debug-monitor.read_only', true)) {
            return;
        }

        if ($sql === '') {
            throw new \RuntimeException('The rule query is empty.');
        }

        // A remaining semicolon means a second (stacked) statement.
        if (str_contains($sql, ';')) {
            throw new \RuntimeException('Only a single SQL statement is allowed.');
        }

        if (! preg_match('/^\s*(SELECT|WITH|SHOW|EXPLAIN)\b/i', $sql)) {
            throw new \RuntimeException('Only read-only queries (SELECT/WITH/SHOW/EXPLAIN) are allowed.');
        }
    }

    /**
     * Apply a per-statement timeout on the current connection where the driver
     * supports it. MySQL's max_execution_time only affects read-only SELECTs,
     * which pairs naturally with the read-only guard above.
     */
    protected function applyTimeout(): void
    {
        $seconds = (int) config('debug-monitor.query_timeout', 0);

        if ($seconds <= 0) {
            return;
        }

        $ms = $seconds * 1000;

        try {
            match (DB::getDriverName()) {
                'mysql', 'mariadb' => DB::statement("SET SESSION max_execution_time = {$ms}"),
                'pgsql' => DB::statement("SET statement_timeout = {$ms}"),
                default => null,
            };
        } catch (\Throwable $e) {
            // Non-fatal: the driver/version may not support it.
            Log::debug('Debug Monitor: could not apply query timeout.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Restore the connection's default timeout so the setting never leaks onto
     * later queries that reuse the same (pooled) connection.
     */
    protected function clearTimeout(): void
    {
        if ((int) config('debug-monitor.query_timeout', 0) <= 0) {
            return;
        }

        try {
            match (DB::getDriverName()) {
                'mysql', 'mariadb' => DB::statement('SET SESSION max_execution_time = 0'),
                'pgsql' => DB::statement('RESET statement_timeout'),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::debug('Debug Monitor: could not clear query timeout.', ['error' => $e->getMessage()]);
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
