<?php

namespace Webmavens\DebugMonitor\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;
use Webmavens\DebugMonitor\Notifications\DebugRuleRecovered;
use Webmavens\DebugMonitor\Support\QueryGuard;

/**
 * Executes a single debug rule: runs its query, evaluates the expectations,
 * writes a log, updates the rule's last-run state, and (optionally) notifies.
 *
 * Shared by the scheduled runner (debug-monitor:run) and the "Run now" UI
 * action so both behave identically.
 *
 * Reliability notes for complex/large queries:
 *  - The query is streamed with a cursor so the full result set is never
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
        $started = hrtime(true);

        try {
            [$errorLog, $sample] = $this->evaluate($rule);
        } catch (\Throwable $e) {
            Log::error("Exception in rule: {$rule->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $exception = $this->errorMessage($e);
            $errorLog = ['exception' => $exception];
            $sample = null;
        }

        $durationMs = $this->elapsedMs($started);
        $isValid = empty($errorLog);
        $result = $this->saveLog($rule, $isValid, $isValid ? $sample : $errorLog, $durationMs);

        if (! $isValid) {
            if ($rule->isSuppressed($now)) {
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
        $rule->last_duration_ms = $durationMs;
        // Keep the start of a failing streak; a pass ends it.
        $rule->failing_since = $isValid ? null : ($rule->failing_since ?? $now);
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
    protected function saveLog(DebugRule $rule, bool $isValid, mixed $result, int $durationMs): mixed
    {
        $status = $isValid ? 'ok' : 'failed';
        $log = ['debug_rule_id' => $rule->id, 'status' => $status, 'duration_ms' => $durationMs];

        try {
            DebugRuleLog::create($log + ['result' => $result]);

            return $result;
        } catch (\Throwable $e) {
            Log::error("Could not save the result of rule: {$rule->name}", ['error' => $e->getMessage()]);

            $result = ['exception' => 'The run result could not be saved: '.$this->errorMessage($e)];
            DebugRuleLog::create($log + ['result' => $result]);

            return $result;
        }
    }

    /**
     * Run the rule's query and compare it with the expectations.
     *
     * @return array{0: array, 1: array} [errorLog (empty when the rule passes), result sample]
     */
    /**
     * Try a query and its expectations without saving anything (the form's
     * "Test query" button). No log is written, no alert is sent, and no rule
     * is changed.
     *
     * @param  array{sql_query: string, expected_rows_operator?: string, expected_rows?: int|null, expected_json?: string|array|null}  $attributes
     * @return array{status: string, rows: int|null, sample: array, sample_truncated: bool, problems: string[], error: string|null, duration_ms: int}
     */
    public function test(array $attributes, int $sampleSize = 10): array
    {
        $rule = new DebugRule(array_intersect_key($attributes, array_flip([
            'sql_query', 'expected_rows_operator', 'expected_rows', 'expected_json',
        ])));
        $started = hrtime(true);

        try {
            [$errorLog, $sample, $rows] = $this->evaluate($rule);
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'rows' => null,
                'sample' => [],
                'sample_truncated' => false,
                'problems' => [],
                'error' => $this->errorMessage($e),
                'duration_ms' => $this->elapsedMs($started),
            ];
        }

        $problems = [];

        if (isset($errorLog['row_mismatch'])) {
            $problems[] = "Expected {$errorLog['row_mismatch']['expected']} rows, got {$rows}.";
        }

        if (isset($errorLog['message'])) {
            $problems[] = $errorLog['message'];
        }

        return [
            'status' => $problems === [] ? 'ok' : 'failed',
            'rows' => $rows,
            'sample' => array_slice($sample, 0, $sampleSize),
            'sample_truncated' => $rows > $sampleSize,
            'problems' => $problems,
            'error' => null,
            'duration_ms' => $this->elapsedMs($started),
        ];
    }

    /** Whole milliseconds since an hrtime(true) reading. */
    protected function elapsedMs(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }

    /**
     * Run the rule's query and compare it with the expectations.
     *
     * @return array{0: array, 1: array, 2: int} [errorLog (empty when it passes), sample rows, row count]
     */
    protected function evaluate(DebugRule $rule): array
    {
        $sql = QueryGuard::normalize($rule->sql_query);
        QueryGuard::assertReadOnly($sql);

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

        return [$errorLog, $data['sample'], $actual];
    }

    /**
     * Mail the "started failing" / "recovered" alerts for a scheduled run and
     * keep failure_notified_at in step (saved by the caller). Returns whether
     * a mail was sent.
     */
    protected function sendAlerts(DebugRule $rule, bool $isValid, array $errorLog, CarbonInterface $now): bool
    {
        $alertsEnabled = ! $rule->isSuppressed($now) && $rule->notification_level !== 'none';

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

        foreach ($this->connection()->cursor($sql) as $row) {
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
     * The connection rule queries run on: config('debug-monitor.connection'),
     * e.g. a read-only database user or a replica, or the app's default.
     * Rules and logs themselves always use the default connection.
     */
    protected function connection(): Connection
    {
        return DB::connection(config('debug-monitor.connection') ?: null);
    }

    /**
     * A short reason for the rule log, the UI and the alert mail: the
     * database's own message, without the connection details (host, port,
     * database name, full SQL) that Laravel adds to query errors. The app log
     * keeps the full message.
     */
    protected function errorMessage(\Throwable $e): string
    {
        if ($e instanceof QueryException && $e->getPrevious() !== null) {
            return $e->getPrevious()->getMessage();
        }

        return $e->getMessage();
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
            $db = $this->connection();

            match ($db->getDriverName()) {
                'mysql', 'mariadb' => $db->statement("SET SESSION max_execution_time = {$ms}"),
                'pgsql' => $db->statement("SET statement_timeout = {$ms}"),
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
            $db = $this->connection();

            match ($db->getDriverName()) {
                'mysql', 'mariadb' => $db->statement('SET SESSION max_execution_time = 0'),
                'pgsql' => $db->statement('RESET statement_timeout'),
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
