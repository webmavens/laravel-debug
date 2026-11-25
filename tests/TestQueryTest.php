<?php

namespace Webmavens\DebugMonitor\Tests;

use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleLog;

/** The "Test query" button on the rule form: run the query without saving anything. */
class TestQueryTest extends TestCase
{
    private const THREE_ROWS = 'select 1 as n union all select 2 union all select 3';

    private function tryQuery(array $data)
    {
        return $this->postJson('/debug-monitor/rules/test', array_merge([
            'expected_rows_operator' => '=',
            'expected_rows' => 3,
        ], $data));
    }

    public function test_a_passing_query_returns_its_row_count_sample_and_duration(): void
    {
        $response = $this->tryQuery(['sql_query' => self::THREE_ROWS])->assertOk();

        $response->assertJson([
            'status' => 'ok',
            'rows' => 3,
            'sample' => [['n' => 1], ['n' => 2], ['n' => 3]],
            'sample_truncated' => false,
            'problems' => [],
        ]);
        $this->assertIsInt($response->json('duration_ms'));
    }

    public function test_a_failing_query_explains_why(): void
    {
        $this->tryQuery(['sql_query' => self::THREE_ROWS, 'expected_rows' => 0])
            ->assertOk()
            ->assertJson(['status' => 'failed', 'rows' => 3, 'problems' => ['Expected = 0 rows, got 3.']]);

        $this->tryQuery(['sql_query' => self::THREE_ROWS, 'expected_json' => '{"n": 9}'])
            ->assertOk()
            ->assertJson(['status' => 'failed', 'problems' => ['No rows matched the expected JSON pattern.']]);

        $this->tryQuery(['sql_query' => self::THREE_ROWS, 'expected_json' => '{"n": 2}'])
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_the_sample_is_limited_to_ten_rows(): void
    {
        $response = $this->tryQuery([
            'sql_query' => 'with recursive n(x) as (select 1 union all select x + 1 from n where x < 15) select x from n',
            'expected_rows_operator' => '>',
            'expected_rows' => 0,
        ])->assertOk();

        $response->assertJson(['status' => 'ok', 'rows' => 15, 'sample_truncated' => true]);
        $this->assertCount(10, $response->json('sample'));
    }

    public function test_a_query_error_returns_a_short_message(): void
    {
        $response = $this->tryQuery(['sql_query' => 'select * from zz_missing'])->assertOk();

        $response->assertJson(['status' => 'error', 'rows' => null]);
        $this->assertStringContainsString('no such table: zz_missing', $response->json('error'));
        $this->assertStringNotContainsString('Connection:', $response->json('error'));
    }

    public function test_write_and_invalid_queries_are_rejected_before_running(): void
    {
        $rule = $this->makeRule();

        $this->tryQuery(['sql_query' => 'delete from debug_rules'])
            ->assertStatus(422)
            ->assertJsonPath('errors.sql_query.0', 'Only read-only queries (SELECT/WITH/SHOW/EXPLAIN) are allowed.');

        $this->tryQuery(['sql_query' => ''])->assertStatus(422)->assertJsonValidationErrors('sql_query');
        $this->tryQuery(['sql_query' => 'select 1', 'expected_json' => '{nope'])->assertStatus(422)->assertJsonValidationErrors('expected_json');
        $this->tryQuery(['sql_query' => 'select 1', 'expected_rows_operator' => '!='])->assertStatus(422)->assertJsonValidationErrors('expected_rows_operator');

        $this->assertNotNull($rule->fresh());
    }

    public function test_nothing_is_saved(): void
    {
        $rule = $this->makeRule();

        $this->tryQuery(['sql_query' => self::THREE_ROWS])->assertOk();
        $this->tryQuery(['sql_query' => 'select * from zz_missing'])->assertOk();

        $this->assertSame(1, DebugRule::count());
        $this->assertSame(0, DebugRuleLog::count());
        $this->assertNull($rule->fresh()->last_run_at);
    }

    public function test_create_and_edit_forms_have_the_test_button(): void
    {
        $rule = $this->makeRule();

        foreach (['/debug-monitor/rules/create', "/debug-monitor/rules/{$rule->id}/edit"] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Test query')
                ->assertSee(route('debug-monitor.rules.test'), false);
        }
    }
}
