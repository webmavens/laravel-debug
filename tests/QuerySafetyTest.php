<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;
use Webmavens\DebugMonitor\Services\RuleRunner;

class QuerySafetyTest extends TestCase
{
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rule', 'sql_query' => 'select 1', 'frequency_minutes' => 15, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
        ], $overrides);
    }

    // ---- A rule that can't run is rejected when it is saved ---------------------

    public function test_form_rejects_queries_that_are_not_read_only(): void
    {
        foreach ([
            'DELETE FROM users WHERE 1 = 0' => 'Only read-only queries (SELECT/WITH/SHOW/EXPLAIN) are allowed.',
            'select 1; drop table users' => 'Only a single SQL statement is allowed.',
            "select * into outfile '/tmp/x' from users" => 'The query contains a data-modifying keyword and was blocked.',
        ] as $sql => $message) {
            $this->from('/debug-monitor/rules/create')
                ->post('/debug-monitor/rules', $this->formData(['sql_query' => $sql]))
                ->assertRedirect('/debug-monitor/rules/create')
                ->assertSessionHasErrors(['sql_query' => $message]);
        }

        $this->assertSame(0, DebugRule::count());
    }

    public function test_form_accepts_a_read_only_query_with_a_trailing_semicolon(): void
    {
        $this->post('/debug-monitor/rules', $this->formData(['sql_query' => 'SELECT 1;']))->assertSessionHasNoErrors();

        $this->assertSame(1, DebugRule::count());
    }

    public function test_editing_a_rule_into_a_write_query_is_rejected(): void
    {
        $rule = $this->makeRule();

        $this->put("/debug-monitor/rules/{$rule->id}", $this->formData(['sql_query' => 'UPDATE users SET name = 1']))
            ->assertSessionHasErrors('sql_query');

        $this->assertSame('select 1', $rule->fresh()->sql_query);
    }

    public function test_write_queries_are_allowed_when_read_only_is_off(): void
    {
        config(['debug-monitor.read_only' => false]);

        $this->post('/debug-monitor/rules', $this->formData(['sql_query' => 'DELETE FROM users WHERE 1 = 0']))
            ->assertSessionHasNoErrors();
    }

    public function test_sync_does_not_import_a_rule_file_with_a_write_query(): void
    {
        $path = sys_get_temp_dir().'/dm-guard-'.getmypid();
        File::deleteDirectory($path);
        File::ensureDirectoryExists($path);
        config(['debug-monitor.rules_path' => $path]);
        File::put($path.'/bad.json', json_encode($this->formData(['name' => 'Bad', 'sql_query' => 'DELETE FROM users'])));

        $this->artisan('debug-monitor:sync')
            ->expectsOutputToContain('Only read-only queries (SELECT/WITH/SHOW/EXPLAIN) are allowed.')
            ->assertFailed();

        $this->assertSame(0, DebugRule::count());
        File::deleteDirectory($path);
    }

    public function test_a_write_query_stored_directly_in_the_database_still_never_runs(): void
    {
        $rule = $this->makeRule(['sql_query' => 'DELETE FROM debug_rules']);

        $outcome = app(RuleRunner::class)->run($rule, notify: false);

        $this->assertSame('Only read-only queries (SELECT/WITH/SHOW/EXPLAIN) are allowed.', $outcome['exception']);
        $this->assertSame(1, DebugRule::count());
    }

    // ---- Query errors don't expose connection details ---------------------------

    public function test_query_errors_hide_connection_details_but_the_app_log_keeps_them(): void
    {
        Notification::fake();
        Log::spy();
        $rule = $this->makeRule(['sql_query' => 'select * from zz_missing_table', 'expected_rows' => 0, 'notification_level' => 'default']);

        $message = app(RuleRunner::class)->run($rule, notify: true)['exception'];

        $this->assertStringContainsString('no such table: zz_missing_table', $message);
        foreach (['Connection:', 'Database:', 'SQL:'] as $detail) {
            $this->assertStringNotContainsString($detail, $message);
        }
        $this->assertSame($message, $rule->fresh()->last_debug_log['exception']);
        $this->assertSame($message, $rule->logs()->first()->result['exception']);

        Notification::assertSentOnDemand(DebugRuleAlert::class, function (DebugRuleAlert $alert) {
            return ! str_contains(implode("\n", $alert->toMail(null)->introLines), 'Connection:');
        });

        $this->post("/debug-monitor/rules/{$rule->id}/run")
            ->assertSessionHas('error', fn (string $flash) => ! str_contains($flash, 'Connection:'));

        // The server log still has the full detail for debugging.
        Log::shouldHaveReceived('error')->withArgs(
            fn ($text, $context = []) => str_contains($context['error'] ?? '', 'Connection:')
        );
    }

    // ---- Rule queries can use their own connection ------------------------------

    public function test_rule_queries_can_run_on_a_separate_connection(): void
    {
        config(['database.connections.rules_readonly' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::connection('rules_readonly')->statement('create table widgets (id integer)');
        DB::connection('rules_readonly')->table('widgets')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        $rule = $this->makeRule(['sql_query' => 'select * from widgets', 'expected_rows' => 3]);

        // The app's default connection has no widgets table.
        $this->assertSame('failed', app(RuleRunner::class)->run($rule->fresh(), notify: false)['status']);

        config(['debug-monitor.connection' => 'rules_readonly']);
        $this->assertSame('ok', app(RuleRunner::class)->run($rule->fresh(), notify: false)['status']);

        // Rules and logs stay on the default connection.
        $this->assertSame(2, $rule->logs()->count());
        $this->assertSame('ok', $rule->fresh()->last_status);
    }

    public function test_an_unknown_connection_name_fails_the_rule_with_a_clear_error(): void
    {
        config(['debug-monitor.connection' => 'does_not_exist']);
        $rule = $this->makeRule();

        $outcome = app(RuleRunner::class)->run($rule, notify: false);

        $this->assertSame('failed', $outcome['status']);
        $this->assertStringContainsString('does_not_exist', $outcome['exception']);
    }
}
