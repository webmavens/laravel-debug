<?php

namespace Webmavens\DebugMonitor\Tests;

use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleRunner;

/** How long a rule has been failing, and how long each run takes. */
class FailingSinceAndDurationTest extends TestCase
{
    private function run_(DebugRule $rule): array
    {
        return app(RuleRunner::class)->run($rule->fresh(), notify: false);
    }

    public function test_failing_since_marks_the_start_of_a_failing_streak(): void
    {
        $this->freezeSecond();
        $rule = $this->makeRule(['expected_rows' => 5]);
        $start = now();

        $this->run_($rule);
        $this->assertTrue($rule->fresh()->failing_since->equalTo($start));

        $this->travel(2)->hours();
        $this->run_($rule);
        $this->assertTrue($rule->fresh()->failing_since->equalTo($start)); // still the start

        $rule->update(['expected_rows' => 1]);
        $this->run_($rule);
        $this->assertNull($rule->fresh()->failing_since);
    }

    public function test_a_query_error_counts_as_failing(): void
    {
        $rule = $this->makeRule(['sql_query' => 'select * from zz_missing']);

        $this->run_($rule);

        $this->assertNotNull($rule->fresh()->failing_since);
    }

    public function test_every_run_records_its_duration(): void
    {
        $ok = $this->makeRule(['name' => 'Ok']);
        $error = $this->makeRule(['name' => 'Error', 'sql_query' => 'select * from zz_missing']);

        $this->run_($ok);
        $this->run_($error);

        foreach ([$ok, $error] as $rule) {
            $rule->refresh();
            $this->assertIsInt($rule->last_duration_ms);
            $this->assertGreaterThanOrEqual(0, $rule->last_duration_ms);
            $this->assertSame($rule->last_duration_ms, $rule->logs()->first()->duration_ms);
        }
    }

    public function test_the_list_shows_how_long_a_rule_has_been_failing(): void
    {
        $rule = $this->makeRule(['name' => 'Broken', 'expected_rows' => 5]);

        $this->run_($rule);
        // A first failure is already said by "Failed · 1 second ago".
        $this->get('/debug-monitor/rules')->assertSee('Failed')->assertDontSee('Failing for');

        $this->travel(3)->days();
        $this->run_($rule);
        $this->get('/debug-monitor/rules')->assertSee('Failing for 3 days');
        $this->get("/debug-monitor/rules/{$rule->id}")->assertSee('Failing since');

        $rule->update(['expected_rows' => 1]);
        $this->run_($rule);
        $this->get('/debug-monitor/rules')->assertSee('Passed')->assertDontSee('Failing for');
    }

    public function test_the_rule_page_shows_run_durations(): void
    {
        $rule = $this->makeRule();
        $this->run_($rule);
        $rule->logs()->update(['duration_ms' => 1534]);
        $rule->update(['last_duration_ms' => 1534]);

        $this->get("/debug-monitor/rules/{$rule->id}")
            ->assertOk()
            ->assertSee('Duration')
            ->assertSee('1.53 s');
    }

    public function test_the_dashboard_lists_the_slowest_rules(): void
    {
        $this->makeRule(['name' => 'Quick', 'last_duration_ms' => 12]);
        $this->makeRule(['name' => 'Sluggish', 'last_duration_ms' => 4200]);
        $this->makeRule(['name' => 'Medium', 'last_duration_ms' => 640]);
        $this->makeRule(['name' => 'Never ran']);
        $this->makeRule(['name' => 'Paused', 'status' => 'inactive', 'last_duration_ms' => 9000]);

        $this->get('/debug-monitor/dashboard')
            ->assertOk()
            ->assertSee('Slowest Rules')
            ->assertSeeInOrder(['Sluggish', '4.2 s', 'Medium', '640 ms', 'Quick', '12 ms'])
            ->assertDontSee('Never ran')
            ->assertDontSee('Paused');
    }

    public function test_no_slowest_rules_section_before_any_run(): void
    {
        $this->makeRule();

        $this->get('/debug-monitor/dashboard')->assertOk()->assertDontSee('Slowest Rules');
    }
}
