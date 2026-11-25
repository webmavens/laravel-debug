<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\File;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleSyncService;

/** Rule groups, searching anywhere in a rule, and sortable list columns. */
class GroupsSearchSortTest extends TestCase
{
    private string $rulesPath;

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $this->rulesPath = sys_get_temp_dir().'/dm-groups-'.getmypid();
        $app['config']->set('debug-monitor.rules_path', $this->rulesPath);
    }

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory($this->rulesPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->rulesPath);
        parent::tearDown();
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rule', 'sql_query' => 'select 1', 'frequency_minutes' => 15, 'importance_level' => 'low',
            'expected_rows_operator' => '=', 'expected_rows' => 1, 'notification_level' => 'none', 'status' => 'active',
        ], $overrides);
    }

    // ---- Groups -----------------------------------------------------------------

    public function test_a_rule_can_be_saved_with_a_group(): void
    {
        $this->post('/debug-monitor/rules', $this->formData(['name' => 'Invoice1', 'group_name' => '  Invoices  ']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Invoices', DebugRule::first()->group_name);

        $rule = DebugRule::first();
        $this->put("/debug-monitor/rules/{$rule->id}", $this->formData(['name' => 'Invoice1', 'group_name' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($rule->fresh()->group_name);

        $this->post('/debug-monitor/rules', $this->formData(['group_name' => str_repeat('g', 101)]))
            ->assertSessionHasErrors('group_name');
    }

    public function test_the_form_suggests_existing_groups(): void
    {
        $this->makeRule(['name' => 'A', 'group_name' => 'Stock']);
        $this->makeRule(['name' => 'B', 'group_name' => 'Invoices']);

        $this->get('/debug-monitor/rules/create')
            ->assertOk()
            ->assertSee('name="group_name"', false)
            ->assertSeeInOrder(['<option value="Invoices">', '<option value="Stock">'], false);
    }

    public function test_the_list_shows_groups_and_filters_by_group(): void
    {
        $this->makeRule(['name' => 'Invoice rule', 'group_name' => 'Invoices']);
        $this->makeRule(['name' => 'Stock rule', 'group_name' => 'Stock']);
        $this->makeRule(['name' => 'Loose rule']);

        $this->get('/debug-monitor/rules')
            ->assertOk()
            ->assertSee('All groups')
            ->assertSeeInOrder(['Invoices', 'Stock', 'No group'])
            ->assertSee('Invoice rule')->assertSee('Stock rule')->assertSee('Loose rule');

        $this->get('/debug-monitor/rules?group=Invoices')
            ->assertSee('Invoice rule')->assertDontSee('Stock rule')->assertDontSee('Loose rule');

        $this->get('/debug-monitor/rules?group=__none__')
            ->assertSee('Loose rule')->assertDontSee('Invoice rule')->assertDontSee('Stock rule');
    }

    public function test_no_group_filter_without_groups(): void
    {
        $this->makeRule();

        $this->get('/debug-monitor/rules')->assertOk()->assertDontSee('All groups');
    }

    public function test_bulk_set_group_updates_rules_and_their_files(): void
    {
        $a = $this->makeRule(['name' => 'A']);
        $b = $this->makeRule(['name' => 'B', 'group_name' => 'Old']);
        $service = app(RuleSyncService::class);
        $file = $service->export($a)['file'];

        $this->post('/debug-monitor/rules/bulk', ['action' => 'group', 'ids' => [$a->id, $b->id], 'group_name' => 'Stock'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Moved 2 rule(s) to group "Stock".');

        $this->assertSame('Stock', $a->fresh()->group_name);
        $this->assertSame('Stock', $b->fresh()->group_name);
        $this->assertSame('Stock', json_decode(File::get($this->rulesPath.'/'.$file), true)['group_name']);
        $this->assertSame(0, $service->pendingCount());

        $this->post('/debug-monitor/rules/bulk', ['action' => 'group', 'ids' => [$a->id], 'group_name' => ''])
            ->assertSessionHas('success', 'Removed 1 rule(s) from their group.');
        $this->assertNull($a->fresh()->group_name);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'group', 'ids' => [$a->id], 'group_name' => str_repeat('g', 101)])
            ->assertSessionHas('error', 'A group name can be at most 100 characters.');
    }

    public function test_rule_files_carry_the_group(): void
    {
        File::ensureDirectoryExists($this->rulesPath);
        File::put($this->rulesPath.'/stock.json', json_encode($this->formData(['name' => 'Stock check', 'group_name' => 'Stock'])));
        File::put($this->rulesPath.'/old.json', json_encode($this->formData(['name' => 'Old file'])));

        $this->artisan('debug-monitor:sync')->assertSuccessful();

        $this->assertSame('Stock', DebugRule::where('name', 'Stock check')->value('group_name'));
        $this->assertNull(DebugRule::where('name', 'Old file')->value('group_name'));
    }

    public function test_editing_a_file_rule_keeps_numbers_as_numbers_in_its_file(): void
    {
        $rule = $this->makeRule(['name' => 'Stock9', 'frequency_minutes' => 15, 'expected_rows' => 0]);
        $file = app(RuleSyncService::class)->export($rule)['file'];

        // Form fields arrive as strings ("100", "0").
        $this->put("/debug-monitor/rules/{$rule->id}", $this->formData([
            'name' => 'Stock9', 'group_name' => 'Stock', 'frequency_minutes' => '100', 'expected_rows' => '0',
        ]))->assertSessionHasNoErrors();

        $json = json_decode(File::get($this->rulesPath.'/'.$file), true);
        $this->assertSame(100, $json['frequency_minutes']);
        $this->assertSame(0, $json['expected_rows']);
        $this->assertSame('Stock', $json['group_name']);
    }

    // ---- Search anywhere ----------------------------------------------------------

    public function test_search_matches_anywhere_in_the_name_group_or_sql(): void
    {
        $this->makeRule(['name' => 'Invoice9: store_balace of store']);
        $this->makeRule(['name' => 'Orders without lines', 'sql_query' => 'select * from orders where total < 0']);
        $this->makeRule(['name' => 'Grouped', 'group_name' => 'Purchasing']);
        $this->makeRule(['name' => 'Unrelated']);

        $this->get('/debug-monitor/rules?search=store')->assertSee('Invoice9: store_balace of store')->assertDontSee('Unrelated');
        $this->get('/debug-monitor/rules?search=STORE')->assertSee('Invoice9: store_balace of store');
        $this->get('/debug-monitor/rules?search=total < 0')->assertSee('Orders without lines')->assertDontSee('Unrelated');
        $this->get('/debug-monitor/rules?search=chasing')->assertSee('Grouped')->assertDontSee('Unrelated');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->makeRule(['name' => 'a_b rule']);
        $this->makeRule(['name' => 'axb rule']);
        $this->makeRule(['name' => '100% done']);
        $this->makeRule(['name' => '1000 done']);

        $this->get('/debug-monitor/rules?search=a_b')->assertSee('a_b rule')->assertDontSee('axb rule');
        $this->get('/debug-monitor/rules?search=100%25')->assertSee('100% done')->assertDontSee('1000 done');
    }

    // ---- Sorting --------------------------------------------------------------------

    private function sortableRules(): void
    {
        $this->makeRule(['name' => 'Bravo', 'status' => 'inactive', 'last_run_at' => now()->subHours(3)]);
        $this->travel(1)->minutes();
        $this->makeRule(['name' => 'Alpha', 'status' => 'active', 'last_run_at' => now()->subHour()]);
        $this->travel(1)->minutes();
        $this->makeRule(['name' => 'Charlie', 'status' => 'active', 'last_run_at' => null]);
    }

    public function test_list_sorts_by_name_status_and_last_run(): void
    {
        $this->sortableRules();
        $names = fn (string $query) => $this->get('/debug-monitor/rules?'.$query)->assertOk();

        $names('')->assertSeeInOrder(['Charlie', 'Alpha', 'Bravo']); // newest first by default
        $names('sort=name&direction=asc')->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie']);
        $names('sort=name&direction=desc')->assertSeeInOrder(['Charlie', 'Bravo', 'Alpha']);
        $names('sort=status&direction=asc')->assertSeeInOrder(['Charlie', 'Alpha', 'Bravo']); // active first, newest first within
        $names('sort=status&direction=desc')->assertSeeInOrder(['Bravo', 'Charlie', 'Alpha']);
        $names('sort=last_run&direction=desc')->assertSeeInOrder(['Alpha', 'Bravo', 'Charlie']); // never run last
        $names('sort=last_run&direction=asc')->assertSeeInOrder(['Bravo', 'Alpha', 'Charlie']); // never run last
        $names('sort=bogus&direction=sideways')->assertSeeInOrder(['Charlie', 'Alpha', 'Bravo']);
    }

    public function test_sort_links_toggle_direction_and_keep_filters(): void
    {
        $this->sortableRules();

        $this->get('/debug-monitor/rules?search=a&page=1')
            ->assertSee(e(route('debug-monitor.rules.index', ['search' => 'a', 'sort' => 'name', 'direction' => 'asc'])), false)
            ->assertSee(e(route('debug-monitor.rules.index', ['search' => 'a', 'sort' => 'last_run', 'direction' => 'desc'])), false);

        $this->get('/debug-monitor/rules?search=a&sort=name&direction=asc')
            ->assertSee(e(route('debug-monitor.rules.index', ['search' => 'a', 'sort' => 'name', 'direction' => 'desc'])), false)
            ->assertSee('aria-sort="ascending"', false);
    }
}
