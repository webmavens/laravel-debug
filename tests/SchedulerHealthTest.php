<?php

namespace Webmavens\DebugMonitor\Tests;

class SchedulerHealthTest extends TestCase
{
    private const BANNER = 'Rules are not running on schedule';

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'database');
    }

    public function test_no_banner_without_rules_or_when_rules_run_on_time(): void
    {
        $this->get('/debug-monitor/dashboard')->assertOk()->assertDontSee(self::BANNER);

        // 15-minute rule last ran 20 minutes ago: within its 10-minute grace period.
        $this->makeRule(['frequency_minutes' => 15, 'last_run_at' => now()->subMinutes(20)]);
        $this->get('/debug-monitor/dashboard')->assertDontSee(self::BANNER);
    }

    public function test_banner_on_every_page_when_an_active_rule_is_overdue(): void
    {
        $rule = $this->makeRule(['frequency_minutes' => 15, 'last_run_at' => now()->subMinutes(26)]);

        foreach (['/debug-monitor/dashboard', '/debug-monitor/rules', "/debug-monitor/rules/{$rule->id}"] as $url) {
            $this->get($url)->assertOk()
                ->assertSee(self::BANNER)
                ->assertSee('1 active rule is overdue.')
                ->assertSee('php artisan schedule:work')
                ->assertSee('php artisan queue:work');
        }

        $this->makeRule(['name' => 'Second', 'last_run_at' => now()->subDay()]);
        $this->get('/debug-monitor/dashboard')->assertSee('2 active rules are overdue.');
    }

    public function test_a_new_rule_that_never_ran_gets_a_grace_period(): void
    {
        $this->makeRule();
        $this->get('/debug-monitor/dashboard')->assertDontSee(self::BANNER);

        $this->travel(11)->minutes();
        $this->get('/debug-monitor/dashboard')->assertSee(self::BANNER);
    }

    public function test_inactive_rules_are_ignored(): void
    {
        $this->makeRule(['status' => 'inactive', 'last_run_at' => now()->subDay()]);

        $this->get('/debug-monitor/dashboard')->assertDontSee(self::BANNER);
    }

    public function test_no_queue_worker_hint_when_rules_run_without_a_worker(): void
    {
        $this->makeRule(['last_run_at' => now()->subDay()]);

        config(['debug-monitor.queue_runs' => false]);
        $this->get('/debug-monitor/dashboard')->assertSee(self::BANNER)->assertDontSee('queue:work');

        config(['debug-monitor.queue_runs' => true, 'queue.default' => 'sync']);
        $this->get('/debug-monitor/dashboard')->assertSee(self::BANNER)->assertDontSee('queue:work');
    }

    public function test_the_banner_clears_once_rules_run_again(): void
    {
        $rule = $this->makeRule(['last_run_at' => now()->subDay()]);
        $this->get('/debug-monitor/dashboard')->assertSee(self::BANNER);

        $this->post("/debug-monitor/rules/{$rule->id}/run");
        $this->get('/debug-monitor/dashboard')->assertDontSee(self::BANNER);
    }
}
