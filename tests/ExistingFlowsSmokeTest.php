<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleSync;

class ExistingFlowsSmokeTest extends TestCase
{
    private string $rulesPath;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $this->rulesPath = sys_get_temp_dir().'/dm-smoke-'.getmypid();
        $app['config']->set('debug-monitor.rules_path', $this->rulesPath);
    }

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory($this->rulesPath);
    }

    public function test_sync_command_imports_stub_file_and_rule_runs(): void
    {
        File::ensureDirectoryExists($this->rulesPath);
        File::copy(
            __DIR__.'/../stubs/debug-monitor-rules/example-missing-user-emails.json',
            $this->rulesPath.'/example-missing-user-emails.json'
        );

        $this->artisan('debug-monitor:sync')->assertSuccessful();
        $rule = DebugRule::first();
        $this->assertNotNull($rule);
        $this->assertNull($rule->last_status);
        $this->assertNull($rule->failure_notified_at);
        $this->assertSame(1, DebugRuleSync::count());

        // Sync is idempotent and nothing is pending afterwards.
        $this->artisan('debug-monitor:sync')->assertSuccessful();
        $this->assertSame(1, DebugRule::count());

        // Rule-file JSON never contains runtime columns.
        $json = json_decode(File::get($this->rulesPath.'/example-missing-user-emails.json'), true);
        $this->assertArrayNotHasKey('last_status', $json);
    }

    public function test_export_command_and_ui_sync_button(): void
    {
        $rule = DebugRule::create([
            'name' => 'Exported rule', 'sql_query' => 'select 1', 'frequency_minutes' => 5, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
            'last_status' => 'failed', 'failure_notified_at' => now(),
        ]);

        $this->artisan('debug-monitor:export', ['--id' => [$rule->id]])->assertSuccessful();
        $files = File::glob($this->rulesPath.'/*.json');
        $this->assertCount(1, $files);
        $json = json_decode(File::get($files[0]), true);
        $this->assertSame('Exported rule', $json['name']);
        $this->assertArrayNotHasKey('last_status', $json);
        $this->assertArrayNotHasKey('failure_notified_at', $json);

        $this->get('/debug-monitor/rules')->assertOk();
        $this->post('/debug-monitor/rules/sync')->assertRedirect(route('debug-monitor.rules.index'))
            ->assertSessionHas('success');
    }

    public function test_clean_command_deletes_old_logs_only(): void
    {
        $rule = DebugRule::create([
            'name' => 'r', 'sql_query' => 'select 1', 'frequency_minutes' => 5, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
        ]);
        foreach ([40, 10, 1] as $days) {
            DB::table('debug_rule_logs')->insert(['debug_rule_id' => $rule->id, 'status' => 'ok', 'result' => '[]', 'created_at' => now()->subDays($days), 'updated_at' => now()]);
        }

        $this->artisan('debug-monitor:clean')->assertSuccessful();
        $this->assertSame(2, DB::table('debug_rule_logs')->count());

        $this->artisan('debug-monitor:clean', ['--days' => 5])->assertSuccessful();
        $this->assertSame(1, DB::table('debug_rule_logs')->count());
    }

    public function test_every_page_renders(): void
    {
        $rule = DebugRule::create([
            'name' => 'r', 'sql_query' => 'select 1', 'frequency_minutes' => 5, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
            'expected_json' => '{"a": 1}',
        ]);

        foreach (['/debug-monitor/dashboard', '/debug-monitor/rules', '/debug-monitor/rules/create',
            "/debug-monitor/rules/{$rule->id}/edit", "/debug-monitor/rules/{$rule->id}"] as $url) {
            $this->get($url)->assertOk();
        }

        // select 1 has no column "a", so the expected JSON can't match.
        $this->post("/debug-monitor/rules/{$rule->id}/run")
            ->assertSessionHas('error', 'Rule "r" failed — see the rule\'s logs for details.');
        $this->assertSame('No rows matched the expected JSON pattern.', $rule->fresh()->last_debug_log['message']);

        $rule->update(['sql_query' => 'select 1 as a']);
        $this->post("/debug-monitor/rules/{$rule->id}/run")->assertSessionHas('success', 'Rule "r" ran and passed.');
        $this->assertSame('ok', $rule->fresh()->last_status);
        $this->post("/debug-monitor/rules/{$rule->id}/suppress", ['action' => 'suppress', 'suppress_notes' => 'x'])->assertSessionHas('success');
        $this->assertTrue($rule->fresh()->suppress);
    }
}
