<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Webmavens\DebugMonitor\Jobs\RunDebugRuleJob;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleSync;
use Webmavens\DebugMonitor\Services\RuleRunner;
use Webmavens\DebugMonitor\Services\RuleSyncService;

class RulesUiTest extends TestCase
{
    private string $rulesPath;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'database');
        $this->rulesPath = sys_get_temp_dir().'/dm-rules-'.getmypid();
        $app['config']->set('debug-monitor.rules_path', $this->rulesPath);
    }

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory($this->rulesPath);
    }

    /** Rule N is created N minutes after rule 1, so latest-first order is rule N..1. */
    private function makeRules(int $count, array $overrides = []): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $rule = DebugRule::create(array_merge([
                'name' => "Rule {$i}",
                'sql_query' => 'select 1',
                'frequency_minutes' => 15,
                'importance_level' => 'medium',
                'expected_rows_operator' => '=',
                'expected_rows' => 1,
                'notification_level' => 'none',
                'status' => 'active',
            ], $overrides));
            $rule->created_at = now()->subDay()->addMinutes($i);
            $rule->save();
        }
    }

    private function idOnPage(int $page): int
    {
        return DebugRule::latest()->get()->values()[($page - 1) * 10]->id;
    }

    private function ruleIds(): array
    {
        return DebugRule::orderBy('id')->get()->all();
    }

    // ---- Same page after actions -------------------------------------------

    public function test_index_renders_checkboxes_status_toggle_and_bulk_form(): void
    {
        $this->makeRules(3);

        $html = $this->get('/debug-monitor/rules')->assertOk()->getContent();

        $this->assertStringContainsString('id="bulk-form"', $html);
        $this->assertStringContainsString(route('debug-monitor.rules.bulk'), $html);
        $this->assertSame(3, substr_count($html, 'name="ids[]"'));
        $this->assertSame(3, substr_count($html, 'form="bulk-form"'));
        $this->assertStringContainsString(route('debug-monitor.rules.status', DebugRule::first()->id), $html);
        $this->assertStringContainsString('Click to deactivate this rule', $html);
        foreach (['run', 'export', 'activate', 'deactivate', 'suppress', 'unsuppress', 'delete'] as $action) {
            $this->assertStringContainsString('name="action" value="'.$action.'"', $html);
        }
    }

    public function test_export_returns_to_same_page_and_filters(): void
    {
        $this->makeRules(25);
        $this->get('/debug-monitor/rules?per_page=10&page=2&status=active')->assertOk();

        $this->post('/debug-monitor/rules/'.$this->idOnPage(2).'/export')
            ->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 2, 'status' => 'active']));
    }

    public function test_single_delete_returns_to_same_page(): void
    {
        $this->makeRules(25);
        $this->get('/debug-monitor/rules?per_page=10&page=2')->assertOk();

        $this->delete('/debug-monitor/rules/'.$this->idOnPage(2))
            ->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 2]));
    }

    public function test_deleting_last_rule_on_last_page_lands_on_previous_page(): void
    {
        $this->makeRules(11); // page 2 holds exactly one rule
        $this->get('/debug-monitor/rules?per_page=10&page=2&importance_level=medium')->assertOk();

        $this->delete('/debug-monitor/rules/'.$this->idOnPage(2))
            ->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 2, 'importance_level' => 'medium']));

        $this->get('/debug-monitor/rules?per_page=10&page=2&importance_level=medium')
            ->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 1, 'importance_level' => 'medium']));
    }

    public function test_edit_go_back_and_update_keep_page(): void
    {
        $this->makeRules(25);
        $this->get('/debug-monitor/rules?per_page=10&page=3')->assertOk();
        $rule = DebugRule::first();

        $expected = route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 3]);

        $this->get("/debug-monitor/rules/{$rule->id}/edit")->assertOk()->assertSee('href="'.e($expected).'"', false);
        $this->get("/debug-monitor/rules/{$rule->id}")->assertOk()->assertSee('href="'.e($expected).'"', false);

        $this->put("/debug-monitor/rules/{$rule->id}", array_merge($rule->only([
            'sql_query', 'frequency_minutes', 'importance_level', 'expected_rows_operator', 'expected_rows', 'notification_level', 'status',
        ]), ['name' => 'Renamed']))->assertRedirect($expected);
    }

    public function test_store_keeps_filters_but_goes_to_page_one(): void
    {
        $this->makeRules(25);
        $this->get('/debug-monitor/rules?per_page=10&page=3&status=active')->assertOk();

        $this->post('/debug-monitor/rules', [
            'name' => 'New', 'sql_query' => 'select 1', 'frequency_minutes' => 5, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
        ])->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'status' => 'active']));
    }

    public function test_without_visiting_index_redirects_to_plain_index(): void
    {
        $this->makeRules(1);
        $this->delete('/debug-monitor/rules/'.DebugRule::first()->id)
            ->assertRedirect(route('debug-monitor.rules.index'));
    }

    // ---- Quick status --------------------------------------------------------

    public function test_toggle_status_flips_and_rewrites_linked_file(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        $file = app(RuleSyncService::class)->export($rule)['file'];

        $this->from('/debug-monitor/rules?per_page=10&page=1')
            ->post("/debug-monitor/rules/{$rule->id}/status")
            ->assertRedirect('/debug-monitor/rules?per_page=10&page=1')
            ->assertSessionHas('success', 'Rule "Rule 1" is now inactive.');

        $this->assertSame('inactive', $rule->fresh()->status);
        $this->assertSame('inactive', json_decode(File::get($this->rulesPath.'/'.$file), true)['status']);
        $this->assertSame(md5_file($this->rulesPath.'/'.$file), DebugRuleSync::first()->checksum);
        $this->assertSame(0, app(RuleSyncService::class)->pendingCount());

        $this->post("/debug-monitor/rules/{$rule->id}/status");
        $this->assertSame('active', $rule->fresh()->status);
    }

    // ---- Bulk run / export / delete -----------------------------------------

    public function test_bulk_run_queues_one_job_per_rule_and_returns_to_page(): void
    {
        Queue::fake();
        $this->makeRules(25);
        $this->get('/debug-monitor/rules?per_page=10&page=2')->assertOk();
        $ids = DebugRule::latest()->skip(10)->take(3)->pluck('id')->all();

        $this->post('/debug-monitor/rules/bulk', ['action' => 'run', 'ids' => $ids])
            ->assertRedirect(route('debug-monitor.rules.index', ['per_page' => 10, 'page' => 2]))
            ->assertSessionHas('success', 'Queued 3 rule(s) to run in the background. Check Last Run and the rule logs for results.');

        Queue::assertPushed(RunDebugRuleJob::class, 3);
        Queue::assertPushed(RunDebugRuleJob::class, fn ($job) => $job->ruleId === $ids[0]);
    }

    public function test_bulk_run_through_sync_queue_actually_runs_rules(): void
    {
        config(['queue.default' => 'sync']);
        $this->makeRules(2);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'run', 'ids' => DebugRule::pluck('id')->all()])
            ->assertSessionHas('success', "Ran 2 rule(s). Check each rule's logs for the results.");

        $this->assertSame(0, DebugRule::whereNull('last_run_at')->count());
        $this->assertSame(['ok', 'ok'], DebugRule::pluck('last_status')->all());
    }

    public function test_run_job_runs_rule_and_ignores_deleted_rule(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();

        (new RunDebugRuleJob($rule->id))->handle(app(RuleRunner::class));
        $this->assertNotNull($rule->fresh()->last_run_at);
        $this->assertSame(1, $rule->logs()->count());

        (new RunDebugRuleJob(999999))->handle(app(RuleRunner::class));
        $this->assertSame(1, DebugRule::count());
    }

    public function test_bulk_export_skips_file_rules(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        app(RuleSyncService::class)->export($a);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'export', 'ids' => [$a->id, $b->id, $c->id]])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Exported 2 rule(s) to files:') && str_ends_with($m, 'Skipped 1 already linked to a file.'));

        $this->assertCount(3, File::glob($this->rulesPath.'/*.json'));

        $this->post('/debug-monitor/rules/bulk', ['action' => 'export', 'ids' => [$a->id]])
            ->assertSessionHas('error', 'Nothing exported: the selected rules are already linked to files.');
    }

    public function test_bulk_delete_removes_rules_and_linked_files(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        app(RuleSyncService::class)->export($a);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', 'Deleted 2 rule(s).');

        $this->assertSame([$c->id], DebugRule::pluck('id')->all());
        $this->assertCount(0, File::glob($this->rulesPath.'/*.json'));
        $this->assertSame(0, DebugRuleSync::count());
    }

    public function test_bulk_rejects_bad_input(): void
    {
        $this->makeRules(1);
        $id = DebugRule::first()->id;

        $this->post('/debug-monitor/rules/bulk', ['action' => 'run'])->assertSessionHas('error', 'Select at least one rule and an action.');
        $this->post('/debug-monitor/rules/bulk', ['action' => 'drop', 'ids' => [$id]])->assertSessionHas('error');
        $this->post('/debug-monitor/rules/bulk', ['action' => 'run', 'ids' => [999999]])->assertSessionHas('error', 'The selected rules no longer exist.');
        $this->assertSame(1, DebugRule::count());
    }

    // ---- Bulk activate / deactivate / suppress / unsuppress -----------------

    public function test_bulk_activate_deactivate_rewrites_linked_files(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        $c->update(['status' => 'inactive']);
        $file = app(RuleSyncService::class)->export($a)['file'];

        $this->post('/debug-monitor/rules/bulk', ['action' => 'deactivate', 'ids' => [$a->id, $b->id, $c->id]])
            ->assertSessionHas('success', 'Deactivated 2 rule(s). Skipped 1 already inactive.');
        $this->assertSame(['inactive', 'inactive', 'inactive'], DebugRule::orderBy('id')->pluck('status')->all());
        $this->assertSame('inactive', json_decode(File::get($this->rulesPath.'/'.$file), true)['status']);
        $this->assertSame(0, app(RuleSyncService::class)->pendingCount());

        $this->post('/debug-monitor/rules/bulk', ['action' => 'activate', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', 'Activated 2 rule(s).');
        $this->assertSame(['active', 'active', 'inactive'], DebugRule::orderBy('id')->pluck('status')->all());
        $this->assertSame('active', json_decode(File::get($this->rulesPath.'/'.$file), true)['status']);
    }

    public function test_bulk_suppress_and_unsuppress(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        $c->update(['suppress' => true, 'suppress_notes' => 'keep my note']);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'suppress', 'ids' => [$a->id, $b->id, $c->id]])
            ->assertSessionHas('success', 'Suppressed 2 rule(s). Skipped 1 already suppressed.');
        $this->assertSame([true, true, true], DebugRule::orderBy('id')->pluck('suppress')->all());
        $this->assertSame('keep my note', $c->fresh()->suppress_notes);
        $this->assertSame('Suppressed Manually', $a->fresh()->suppress_notes);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'unsuppress', 'ids' => [$a->id, $c->id]])
            ->assertSessionHas('success', 'Unsuppressed 2 rule(s).');
        $this->assertSame([false, true, false], DebugRule::orderBy('id')->pluck('suppress')->all());
        $this->assertNull($a->fresh()->suppress_notes);
    }

    // ---- Last result + Failing filter ---------------------------------------

    public function test_runner_records_last_status(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        $runner = app(RuleRunner::class);

        $runner->run($rule, notify: false);
        $this->assertSame('ok', $rule->fresh()->last_status);

        $rule->update(['expected_rows' => 5]);
        $runner->run($rule->fresh(), notify: false);
        $this->assertSame('failed', $rule->fresh()->last_status);

        $rule->update(['expected_rows' => 1]);
        $runner->run($rule->fresh(), notify: false);
        $this->assertSame('ok', $rule->fresh()->last_status);

        $rule->update(['sql_query' => 'select * from missing_table']);
        $runner->run($rule->fresh(), notify: false);
        $this->assertSame('failed', $rule->fresh()->last_status);
    }

    public function test_failing_filter_and_result_badges(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        $a->update(['last_status' => 'failed', 'last_run_at' => now()->subMinutes(2)]);
        $b->update(['last_status' => 'ok', 'last_run_at' => now()->subMinutes(3)]);

        $html = $this->get('/debug-monitor/rules?status=failing')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="ids[]"'));
        $this->assertStringContainsString('Select rule Rule 1"', $html);
        $this->assertMatchesRegularExpression('/<option value="failing"\s+selected/', $html);

        $all = $this->get('/debug-monitor/rules')->getContent();
        $this->assertSame(1, preg_match_all('/>\s*Failed\s*</', $all));
        $this->assertSame(1, preg_match_all('/>\s*Passed\s*</', $all));
        $this->assertSame(1, preg_match_all('/>\s*Never\s*</', $all));
    }

    public function test_create_migration_has_last_status(): void
    {
        $this->assertTrue(Schema::hasColumn('debug_rules', 'last_status'));
        $this->assertContains('debug_rules_last_status_status_created_at_index', array_column(Schema::getIndexes('debug_rules'), 'name'));
    }

    // ---- Dashboard (item 8) ---------------------------------------------------

    private function addLog(DebugRule $rule, string $status, $at, array $result = []): void
    {
        DB::table('debug_rule_logs')->insert([
            'debug_rule_id' => $rule->id, 'status' => $status, 'result' => json_encode($result),
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function test_dashboard_groups_failures_per_rule_within_24_hours(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();

        for ($i = 5; $i >= 1; $i--) {
            $this->addLog($a, 'failed', now()->subMinutes($i * 10), ['message' => "failure {$i}"]);
        }
        $this->addLog($a, 'ok', now()->subMinutes(1));
        $this->addLog($b, 'failed', now()->subHours(3), ['exception' => 'boom']);
        $this->addLog($c, 'failed', now()->subDays(2), ['message' => 'too old']);

        $response = $this->get('/debug-monitor/dashboard')->assertOk();
        $rows = $response->viewData('recentErrors');

        $this->assertSame(['Rule 1', 'Rule 2'], collect($rows->items())->map(fn ($log) => $log->rule->name)->all());
        $this->assertSame([5, 1], collect($rows->items())->pluck('failures')->all());
        $this->assertSame('failure 1', $rows->items()[0]->result['message']); // latest failure's details
        $response->assertSee('5 failures')->assertSee('1 failure')->assertSee('boom')->assertDontSee('too old');
    }

    public function test_dashboard_paginates_and_query_count_does_not_grow_with_rows(): void
    {
        $this->makeRules(15);
        foreach ($this->ruleIds() as $i => $rule) {
            $this->addLog($rule, 'failed', now()->subMinutes(100 - $i));
            $this->addLog($rule, 'failed', now()->subMinutes(50 - $i));
        }

        $page1 = $this->get('/debug-monitor/dashboard')->assertOk()->viewData('recentErrors');
        $this->assertCount(10, $page1->items());
        $this->assertSame(15, $page1->total());
        $this->assertSame('Rule 15', $page1->items()[0]->rule->name); // most recent failure first

        $page2 = $this->get('/debug-monitor/dashboard?page=2')->assertOk()->viewData('recentErrors');
        $this->assertCount(5, $page2->items());

        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/debug-monitor/dashboard')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $before = $count();

        $this->makeRules(30);
        foreach (DebugRule::orderByDesc('id')->take(30)->get() as $rule) {
            $this->addLog($rule, 'failed', now()->subMinutes(5));
        }
        $this->assertSame($before, $count());
    }

    public function test_dashboard_does_not_send_sample_rows(): void
    {
        $this->makeRules(2);
        [$a, $b] = $this->ruleIds();
        $sample = array_map(fn ($i) => ['email' => "sample-row-{$i}@example.test"], range(1, 200));
        $this->addLog($a, 'failed', now()->subMinutes(5), ['row_mismatch' => [
            'query' => 'select 1', 'expected' => '= 0', 'actual' => 200, 'result' => $sample,
        ]]);
        $this->addLog($b, 'failed', now()->subMinutes(6), ['row_mismatch' => [
            'query' => 'select 2', 'expected' => '= 0', 'actual' => 1, // no 'result' key
        ]]);

        $this->get('/debug-monitor/dashboard')->assertOk()
            ->assertSee('Actual: 200')
            ->assertDontSee('sample-row-1@example.test');
    }

    // ---- Rule logs pagination (item 9) --------------------------------------

    public function test_show_page_paginates_logs_newest_first(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        for ($i = 1; $i <= 45; $i++) {
            $this->addLog($rule, $i % 2 ? 'ok' : 'failed', now()->subMinutes(100 - $i), ['n' => $i]);
        }

        $page1 = $this->get("/debug-monitor/rules/{$rule->id}")->assertOk()->viewData('logs');
        $this->assertCount(20, $page1->items());
        $this->assertSame(45, $page1->total());
        $this->assertSame(45, $page1->items()[0]->result['n']);

        $page3 = $this->get("/debug-monitor/rules/{$rule->id}?page=3")->assertOk();
        $this->assertSame([5, 4, 3, 2, 1], collect($page3->viewData('logs')->items())->map(fn ($l) => $l->result['n'])->all());
        $page3->assertSee('?page=2', false);
    }

    public function test_deleting_last_log_on_last_page_goes_to_previous_page(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        for ($i = 1; $i <= 21; $i++) {
            $this->addLog($rule, 'ok', now()->subMinutes(100 - $i));
        }
        $oldest = $rule->logs()->orderBy('id')->first();

        $this->from("/debug-monitor/rules/{$rule->id}?page=2")
            ->delete("/debug-monitor/logs/{$oldest->id}")
            ->assertRedirect("/debug-monitor/rules/{$rule->id}?page=2");

        $this->get("/debug-monitor/rules/{$rule->id}?page=2")
            ->assertRedirect(route('debug-monitor.rules.show', ['rule' => $rule->id, 'page' => 1]));
    }

    // ---- Sidebar failing rules -------------------------------------------------

    public function test_sidebar_lists_only_currently_failing_rules_with_limit(): void
    {
        $this->makeRules(3);
        [$a, $b, $c] = $this->ruleIds();
        $a->update(['last_status' => 'failed', 'last_run_at' => now()->subMinutes(10)]);
        $b->update(['last_status' => 'failed', 'last_run_at' => now()->subMinutes(2)]);
        $c->update(['last_status' => 'ok', 'last_run_at' => now()->subMinutes(1)]);
        $this->addLog($c, 'failed', now()->subHours(1)); // failed before, passing now

        $view = $this->get('/debug-monitor/dashboard')->assertOk();
        $html = $view->getContent();
        $sidebar = substr($html, strpos($html, 'Failing Rules'), 3000);
        $this->assertStringContainsString('Rule 2', $sidebar);
        $this->assertStringContainsString('Rule 1', $sidebar);
        $this->assertStringNotContainsString('Rule 3', $sidebar);
        $this->assertLessThan(strpos($sidebar, 'Rule 1'), strpos($sidebar, 'Rule 2')); // newest failure first
        $this->assertStringNotContainsString('View all failing', $html);

        $this->makeRules(8, ['last_status' => 'failed', 'last_run_at' => now()->subMinute()]);
        $html = $this->get('/debug-monitor/dashboard')->getContent();
        $this->assertSame(2, substr_count($html, 'View all failing')); // mobile + desktop sidebar
        $this->assertStringContainsString(route('debug-monitor.rules.index', ['status' => 'failing']), $html);

        $composer = new \Webmavens\DebugMonitor\View\Composers\RecentFailedRulesComposer();
        $fakeView = \Mockery::mock(\Illuminate\View\View::class);
        $fakeView->shouldReceive('with')->once()->withArgs(function ($data) {
            return $data['recentFailedRules']->count() === 8 && $data['hasMoreFailedRules'] === true;
        });
        $composer->compose($fakeView);
    }

    public function test_sidebar_empty_state(): void
    {
        $this->makeRules(1);
        $this->get('/debug-monitor/dashboard')->assertOk()->assertSee('No rules are failing right now.');
    }

    // ---- Show page result preview ---------------------------------------------

    public function test_show_page_renders_preview_and_loads_full_result_on_demand(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        $sample = array_map(fn ($i) => ['email' => "row-{$i}@example.test"], range(1, 300));
        $this->addLog($rule, 'ok', now()->subMinutes(5), $sample);
        $this->addLog($rule, 'failed', now()->subMinutes(4), ['message' => 'short']);
        [$long, $short] = $rule->logs()->orderBy('id')->get()->all();

        $html = $this->get("/debug-monitor/rules/{$rule->id}")->assertOk()->getContent();
        $this->assertStringContainsString('row-1@example.test', $html);         // preview
        $this->assertStringNotContainsString('row-300@example.test', $html);    // not the full result
        $this->assertSame(1, substr_count($html, 'x-data="logResult('));         // only the long one is expandable
        $this->assertStringContainsString(str_replace('/', '\/', route('debug-monitor.logs.show', $long)), $html);
        $this->assertStringContainsString('{&quot;message&quot;:&quot;short&quot;}', $html);

        $this->getJson(route('debug-monitor.logs.show', $long))->assertOk()->assertJsonCount(300)->assertJsonPath('299.email', 'row-300@example.test');
        $this->getJson(route('debug-monitor.logs.show', $short))->assertOk()->assertExactJson(['message' => 'short']);
        $this->getJson(route('debug-monitor.logs.show', 999999))->assertNotFound();
    }

    public function test_show_page_renders_long_short_and_error_results(): void
    {
        $this->makeRules(1);
        $rule = DebugRule::first();
        $sample = array_map(fn ($i) => ['id' => $i, 'email' => null, 'name' => "User {$i}"], range(1, 40));
        $this->addLog($rule, 'ok', now()->subMinutes(5), $sample);
        $this->addLog($rule, 'failed', now()->subMinutes(4), ['row_mismatch' => ['expected' => '= 0', 'actual' => 40, 'query' => 'select id, email, name from users where email is null', 'result' => $sample]]);
        $this->addLog($rule, 'failed', now()->subMinutes(3), ['exception' => 'SQLSTATE[42S02]: table not found']);

        $this->get('/debug-monitor/rules/'.$rule->id)->assertOk()
            ->assertSee('Show full')
            ->assertSee('SQLSTATE[42S02]: table not found');

        foreach ($rule->logs as $log) {
            $this->getJson('/debug-monitor/logs/'.$log->id)->assertOk();
        }
    }

    // ---- Rows per page -----------------------------------------------------------

    public function test_rules_list_shows_25_rows_by_default(): void
    {
        $this->makeRules(30);
        $response = $this->get('/debug-monitor/rules')->assertOk();

        $this->assertCount(25, $response->viewData('rules')->items());
        $response->assertSee('<option value="25" selected>25 per page</option>', false);
    }

    public function test_rules_list_per_page_choices_and_invalid_values(): void
    {
        $this->makeRules(120);

        foreach ([10 => 10, 25 => 25, 50 => 50, 100 => 100, 7 => 25, 1000 => 25, 'abc' => 25, '' => 25] as $asked => $shown) {
            $rules = $this->get('/debug-monitor/rules?per_page='.$asked)->assertOk()->viewData('rules');
            $this->assertCount($shown, $rules->items(), "per_page={$asked}");
        }

        $this->get('/debug-monitor/rules?per_page=50')
            ->assertSee('<option value="50" selected>50 per page</option>', false)
            ->assertSee('per_page=50&amp;page=2', false); // pagination links keep it
    }

    public function test_rules_with_the_same_created_at_page_in_a_stable_order(): void
    {
        // Rules synced from files are created within the same second.
        $this->travelTo(now()->startOfSecond());
        $this->makeRules(30);
        $newestFirst = DebugRule::orderByDesc('id')->pluck('id')->all();

        $page1 = collect($this->get('/debug-monitor/rules')->viewData('rules')->items())->pluck('id')->all();
        $page2 = collect($this->get('/debug-monitor/rules?page=2')->viewData('rules')->items())->pluck('id')->all();

        $this->assertSame(array_slice($newestFirst, 0, 25), $page1);
        $this->assertSame(array_slice($newestFirst, 25), $page2);

        // SQLite breaks created_at ties by id on its own; MySQL doesn't, so the
        // query itself must order by id as a tie-breaker.
        $sql = [];
        DB::listen(function ($q) use (&$sql) { $sql[] = $q->sql; });
        $this->get('/debug-monitor/rules?status=active&per_page=10')->assertOk();
        $listQuery = collect($sql)->first(fn ($s) => str_contains($s, 'from "debug_rules"') && str_contains($s, 'limit 10'));
        $this->assertStringContainsString('order by "created_at" desc, "id" desc', $listQuery);
    }

    public function test_per_page_is_kept_after_actions_and_out_of_range_pages(): void
    {
        $this->makeRules(30);
        $this->get('/debug-monitor/rules?per_page=10&page=3')->assertOk();
        $id = DebugRule::latest()->skip(20)->take(1)->value('id');

        $this->post('/debug-monitor/rules/bulk', ['action' => 'deactivate', 'ids' => [$id]])
            ->assertRedirect('/debug-monitor/rules?per_page=10&page=3');

        // 30 rules at 50 per page is a single page: page 2 goes back to page 1, keeping per_page.
        $this->get('/debug-monitor/rules?per_page=50&page=2')
            ->assertRedirect('/debug-monitor/rules?per_page=50&page=1');
    }

    // ---- Review fixes ------------------------------------------------------------

    public function test_flash_message_survives_redirect_to_last_real_page(): void
    {
        $this->makeRules(12); // page 2 holds 2 rules
        $this->get('/debug-monitor/rules?per_page=10&page=2')->assertOk();
        $ids = DebugRule::latest()->skip(10)->take(10)->pluck('id')->all();

        $this->followingRedirects()
            ->post('/debug-monitor/rules/bulk', ['action' => 'delete', 'ids' => $ids])
            ->assertOk()
            ->assertSee('Deleted 2 rule(s).');

        $rule = DebugRule::first();
        for ($i = 1; $i <= 21; $i++) {
            $this->addLog($rule, 'ok', now()->subMinutes(100 - $i));
        }
        $oldest = $rule->logs()->orderBy('id')->first();

        $this->from("/debug-monitor/rules/{$rule->id}?page=2")
            ->followingRedirects()
            ->delete("/debug-monitor/logs/{$oldest->id}")
            ->assertOk()
            ->assertSee('Log deleted successfully!');
    }

    public function test_deactivated_failing_rule_is_not_listed_as_failing(): void
    {
        $this->makeRules(2, ['last_status' => 'failed', 'last_run_at' => now()->subMinute()]);
        [$a, $b] = $this->ruleIds();
        $this->post("/debug-monitor/rules/{$b->id}/status"); // deactivate Rule 2

        $html = $this->get('/debug-monitor/rules?status=failing')->assertOk()->getContent();
        $this->assertStringContainsString('Select rule Rule 1"', $html);
        $this->assertStringNotContainsString('Select rule Rule 2"', $html);

        $sidebar = substr($html, strpos($html, 'Failing Rules'), 3000);
        $this->assertStringContainsString('Rule 1', $sidebar);
        $this->assertStringNotContainsString('Rule 2', $sidebar);
    }

    public function test_dashboard_survives_log_deleted_between_its_queries(): void
    {
        $this->makeRules(2);
        [$a, $b] = $this->ruleIds();
        $this->addLog($a, 'failed', now()->subMinutes(5));
        $this->addLog($b, 'failed', now()->subMinutes(6));
        $doomed = $a->logs()->value('id');

        $deleted = false;
        DB::listen(function ($query) use (&$deleted, $doomed) {
            if (! $deleted && str_contains($query->sql, 'latest_log_id') && ! str_contains($query->sql, 'aggregate')) {
                $deleted = true;
                DB::table('debug_rule_logs')->where('id', $doomed)->delete();
            }
        });

        $rows = $this->get('/debug-monitor/dashboard')->assertOk()->viewData('recentErrors');
        $this->assertTrue($deleted);
        $this->assertSame(['Rule 2'], collect($rows->items())->map(fn ($l) => $l->rule->name)->all());
    }

    public function test_log_indexes_exist(): void
    {
        $names = array_column(Schema::getIndexes('debug_rule_logs'), 'name');
        $this->assertContains('debug_rule_logs_debug_rule_id_created_at_index', $names);
        $this->assertContains('debug_rule_logs_status_created_at_index', $names);
    }

    // ---- Every page renders with realistic data --------------------------------

    public function test_pages_render_with_mixed_rules_and_logs(): void
    {
        $this->makeRules(12);
        DebugRule::orderBy('id')->first()->update(['status' => 'inactive']);
        DebugRule::latest()->first()->update(['last_status' => 'ok', 'last_run_at' => now()->subMinutes(1)]);
        DebugRule::latest()->skip(1)->first()->update(['last_status' => 'failed', 'last_run_at' => now()->subMinutes(4)]);

        $this->get('/debug-monitor/rules')->assertOk();
        $this->get('/debug-monitor/rules/'.DebugRule::first()->id.'/edit')->assertOk();

        [$a, $b] = $this->ruleIds();
        for ($i = 1; $i <= 30; $i++) {
            $this->addLog($a, 'failed', now()->subMinutes(200 - $i), ['message' => 'No rows matched the expected JSON pattern.']);
        }
        $this->addLog($b, 'failed', now()->subHours(2), ['row_mismatch' => ['query' => 'select * from users where email is null', 'expected' => '= 0', 'actual' => 3]]);
        $this->get('/debug-monitor/dashboard')->assertOk();
        $this->get('/debug-monitor/rules/'.$a->id.'?page=2')->assertOk();
    }
}
