<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\Notification;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;
use Webmavens\DebugMonitor\Services\RuleRunner;

/** Suppressing a rule for a while, after which it alerts again by itself. */
class TimedSuppressTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('debug-monitor.notify_email', 'alerts@example.test');
    }

    private function failingRule(array $overrides = []): DebugRule
    {
        return $this->makeRule(array_merge(['expected_rows' => 5, 'notification_level' => 'default'], $overrides));
    }

    private function suppress(DebugRule $rule, ?string $duration)
    {
        return $this->post("/debug-monitor/rules/{$rule->id}/suppress", array_filter(
            ['action' => 'suppress', 'duration' => $duration],
            fn ($value) => $value !== null
        ));
    }

    public function test_suppress_for_a_while_sets_an_end_time(): void
    {
        $this->freezeSecond();
        $rule = $this->failingRule();

        $this->suppress($rule, '24')->assertSessionHasNoErrors()->assertRedirect();

        $rule->refresh();
        $this->assertTrue($rule->suppress);
        $this->assertTrue($rule->suppressed_until->equalTo(now()->addHours(24)));
        $this->assertTrue($rule->isSuppressed());

        $this->get('/debug-monitor/rules')->assertSee('Unsuppress')->assertSee('for 1 day');
    }

    public function test_suppress_without_a_duration_lasts_until_unsuppressed(): void
    {
        foreach ([null, 'forever'] as $duration) {
            $rule = $this->failingRule(['name' => 'Rule '.($duration ?? 'none')]);
            $this->suppress($rule, $duration)->assertSessionHasNoErrors();

            $this->assertNull($rule->fresh()->suppressed_until);
            $this->travel(30)->days();
            $this->assertTrue($rule->fresh()->isSuppressed());
        }
    }

    public function test_an_unknown_duration_is_rejected(): void
    {
        $rule = $this->failingRule();

        $this->suppress($rule, '5')->assertSessionHasErrors('duration');
        $this->assertFalse($rule->fresh()->suppress);
    }

    public function test_alerts_resume_when_the_suppression_ends(): void
    {
        Notification::fake();
        $rule = $this->failingRule();
        $this->suppress($rule, '1');

        app(RuleRunner::class)->run($rule->fresh());
        Notification::assertNothingSent();

        $this->travel(61)->minutes();
        $this->assertFalse($rule->fresh()->isSuppressed());

        app(RuleRunner::class)->run($rule->fresh());
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
    }

    public function test_an_ended_suppression_no_longer_counts_anywhere(): void
    {
        $this->freezeSecond();
        $rule = $this->failingRule(['name' => 'Ended']);
        $this->suppress($rule, '1');
        $still = $this->failingRule(['name' => 'Still suppressed']);
        $this->suppress($still, 'forever');

        $this->travel(2)->hours();

        $this->get('/debug-monitor/rules?status=suppressed')->assertSee('Still suppressed')->assertDontSee('Ended');
        $this->get('/debug-monitor/dashboard')->assertOk()->assertViewHas('suppressRules', 1);

        // Its row offers "Suppress" again, and a bulk suppress applies to it.
        $this->post('/debug-monitor/rules/bulk', ['action' => 'suppress', 'ids' => [$rule->id, $still->id], 'duration' => '168'])
            ->assertSessionHas('success', 'Suppressed 1 rule(s). Skipped 1 already suppressed.');
        $this->assertTrue($rule->fresh()->suppressed_until->equalTo(now()->addDays(7)));
    }

    public function test_the_scheduled_run_clears_ended_suppressions(): void
    {
        $rule = $this->failingRule(['notification_level' => 'none']);
        $this->suppress($rule, '1');

        $this->travel(2)->hours();
        $this->artisan('debug-monitor:run', ['--sync' => true])->assertSuccessful();

        $rule->refresh();
        $this->assertFalse($rule->suppress);
        $this->assertNull($rule->suppressed_until);
        $this->assertNull($rule->suppress_notes);
    }

    public function test_unsuppress_clears_the_end_time(): void
    {
        $rule = $this->failingRule();
        $this->suppress($rule, '24');

        $this->post("/debug-monitor/rules/{$rule->id}/suppress", ['action' => 'unsuppress'])->assertSessionHasNoErrors();

        $rule->refresh();
        $this->assertFalse($rule->suppress);
        $this->assertNull($rule->suppressed_until);
    }

    public function test_bulk_suppress_for_a_while(): void
    {
        $this->freezeSecond();
        $a = $this->failingRule(['name' => 'A']);
        $b = $this->failingRule(['name' => 'B']);

        $this->post('/debug-monitor/rules/bulk', ['action' => 'suppress', 'ids' => [$a->id, $b->id], 'duration' => '1'])
            ->assertSessionHas('success', 'Suppressed 2 rule(s).');

        $this->assertTrue($a->fresh()->suppressed_until->equalTo(now()->addHour()));
        $this->assertTrue($b->fresh()->suppressed_until->equalTo(now()->addHour()));

        $this->post('/debug-monitor/rules/bulk', ['action' => 'suppress', 'ids' => [$a->id], 'duration' => '5'])
            ->assertSessionHas('error', 'Choose how long to suppress the rules for.');
    }

    public function test_the_rule_page_offers_durations_and_shows_the_end(): void
    {
        $rule = $this->failingRule();

        $this->get("/debug-monitor/rules/{$rule->id}")
            ->assertOk()
            ->assertSee('name="duration"', false)
            ->assertSee('For 24 hours');

        $this->suppress($rule, '24');

        $this->get("/debug-monitor/rules/{$rule->id}")
            ->assertSee('Suppressed until')
            ->assertSee('Unsuppress');
    }
}
