<?php

namespace Webmavens\DebugMonitor\Support;

/**
 * Decides whether a rule's SQL may run: a single read-only statement.
 *
 * Checked when a rule is saved or synced from a file (so the problem shows up
 * on the form) and again right before every run (so a query edited straight
 * in the database still can't change data).
 */
class QueryGuard
{
    /** Trim the query and drop one trailing semicolon. */
    public static function normalize(mixed $sql): string
    {
        $sql = trim((string) $sql);

        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }

        return $sql;
    }

    /**
     * Throw when a normalized query isn't allowed to run. Does nothing when
     * the read_only setting is off.
     *
     * @throws \RuntimeException with a reason the user can act on
     */
    public static function assertReadOnly(string $sql): void
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

    /** Why the query can't run, or null when it can. */
    public static function problem(mixed $sql): ?string
    {
        try {
            self::assertReadOnly(self::normalize($sql));

            return null;
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }
}
