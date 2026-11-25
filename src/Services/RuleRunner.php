<?php

namespace Webmavens\DebugMonitor\Services;

use Carbon\Carbon;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;
use Webmavens\DebugMonitor\Notifications\DebugRuleRecovered;

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
     * Alerts are sent per failing streak, not per failed run: one alert when a
     * rule starts failing (data that doesn't match *or* a query error) and one
     * "recovered" mail when it passes again. Only notifying (scheduled) runs
     * read or change that alert state, so a manual "Run now" can neither send
     * nor swallow an alert.
     *
     * @param  bool  $notify  Send alerts (scheduled runs) or stay silent
     *                        (manual "Run now" / bulk run from the UI).
     * @return array{status: string, exception: ?string, errorLog: array, notified: bool}
     */
    public function run(DebugRule $rule, bool $notify = true): array
    {
        $now = Carbon::now();
        $exception = null;

        try {
            [$errorLog, $sample] = $this->evaluate($rule);
        } catch (\Throwable $e) {
            Log::error("Exception in rule: {$rule->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $exception = $e->getMessage();
            $errorLog = ['exception' => $exception];
            $sample = null;
        }

        $isValid = empty($errorLog);
        $result = $this->saveLog($rule, $isValid, $isValid ? $sample : $errorLog);

        if (! $isValid) {
            if ($rule->suppress) {
                Log::info("Rule failed but suppressed: {$rule->name}", $errorLog);
            } elseif ($rule->notification_level === 'none') {
                Log::warning("Rule failed (no notification): {$rule->name}", $errorLog);
            } else {
                Log::warning("Rule failed: {$rule->name}", $errorLog);
            }
        }

        $notified = $notify && $this->sendAlerts($rule, $isValid, $errorLog, $now);

        $rule->last_run_at = $now;
        $rule->last_status = $isValid ? 'ok' : 'failed';
        $rule->last_debug_log = $result;
        $rule->save();

        return [
            'status' => $isValid ? 'ok' : 'failed',
            'exception' => $exception,
            'errorLog' => $errorLog,
            'notified' => $notified,
        ];
    }

    /**
     * Save the run's log (capped sample only) and return the result as stored.
     *
     * If the result itself can't be stored (e.g. a binary column that can't be
     * JSON-encoded), a short note is stored instead, so the run is still
     * recorded and last_run_at still moves on.
     */
    protected function saveLog(DebugRule $rule, bool $isValid, mixed $result): mixed
    {
        $status = $isValid ? 'ok' : 'failed';

        try {
            DebugRuleLog::create(['debug_rule_id' => $rule->id, 'result' => $result, 'status' => $status]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("Could not save the result of rule: {$rule->name}", ['error' => $e->getMessage()]);

            $result = ['exception' => 'The run result could not be saved: '.$e->getMessage()];
            DebugRuleLog::create(['debug_rule_id' => $rule->id, 'result' => $result, 'status' => $status]);

            return $result;
        }
    }

    /**
     * Run the rule's query and compare it with the expectations.
     *
     * @return array{0: array, 1: array} [errorLog (empty when the rule passes), result sample]
     */
    protected function evaluate(DebugRule $rule): array
    {
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
            $errorLog['message'] = 'No rows matched the expected JSON pattern.';

            if (! empty($data['mismatches'])) {
                $errorLog['data_mismatch'] = $data['mismatches'];
            }
        }

        return [$errorLog, $data['sample']];
    }

    /**
     * Mail the "started failing" / "recovered" alerts for a scheduled run and
     * keep failure_notified_at in step (saved by the caller). Returns whether
     * a mail was sent.
     */
    protected function sendAlerts(DebugRule $rule, bool $isValid, array $errorLog, Carbon $now): bool
    {
        $alertsEnabled = ! $rule->suppress && $rule->notification_level !== 'none';

        if (! $isValid) {
            // Already alerted for this failing streak, or alerts are off.
            if ($rule->failure_notified_at !== null || ! $alertsEnabled) {
                return false;
            }

            // Only mark the streak as alerted once the mail is sent, so a mail
            // failure is retried on the next run.
            if (! $this->mail($rule, new DebugRuleAlert($rule, $errorLog))) {
                return false;
            }

            $rule->failure_notified_at = $now;

            return true;
        }

        if ($rule->failure_notified_at === null) {
            return false; // nothing outstanding to recover from
        }

        // Keep the streak open until the "recovered" mail is sent, so a mail
        // failure is retried on the next passing run.
        if ($alertsEnabled && ! $this->mail($rule, new DebugRuleRecovered($rule))) {
            return false;
        }

        $rule->failure_notified_at = null;

        return $alertsEnabled;
    }

    protected function mail(DebugRule $rule, BaseNotification $notification): bool
    {
        try {
            // Sent right away, not queued: scheduled runs already run as queued
            // jobs, and sending here means a mail failure is actually seen (and
            // retried on the next run) instead of being lost in a later mail job.
            Notification::route('mail', config('debug-monitor.notify_email'))->notifyNow($notification);

            return true;
        } catch (\Throwable $e) {
            Log::error("Could not send debug-monitor alert for rule: {$rule->name}", [
                'error' => $e->getMessage(),
            ]);

            return false;
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

        // Belt-and-suspenders: reject data-modifying keywords anywhere in the
        // statement. This closes data-modifying CTEs (e.g. Postgres
        // "WITH x AS (DELETE ... RETURNING ...) SELECT ...") that would slip
        // past the leading-keyword check above.
        $forbidden = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER',
            'CREATE', 'REPLACE', 'MERGE', 'GRANT', 'REVOKE', 'CALL', 'INTO',
        ];

        if (preg_match('/\b('.implode('|', $forbidden).')\b/i', $sql)) {
            throw new \RuntimeException('The query contains a data-modifying keyword and was blocked.');
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
