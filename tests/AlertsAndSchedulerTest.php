<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Webmavens\DebugMonitor\Jobs\RunDebugRuleJob;
use Webmavens\DebugMonitor\Jobs\RunScheduledDebugRuleJob;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Notifications\DebugRuleAlert;
use Webmavens\DebugMonitor\Notifications\DebugRuleRecovered;
use Webmavens\DebugMonitor\Services\RuleRunner;

class AlertsAndSchedulerTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('debug-monitor.notify_email', 'alerts@example.test');
    }

    private function rule(array $overrides = []): DebugRule
    {
        return DebugRule::create(array_merge([
            'name' => 'Rule 1',
            'sql_query' => 'select 1',
            'frequency_minutes' => 15,
            'importance_level' => 'high',
            'expected_rows_operator' => '=',
            'expected_rows' => 1,
            'notification_level' => 'default',
            'status' => 'active',
        ], $overrides));
    }

    private function scheduled(DebugRule $rule): array
    {
        return app(RuleRunner::class)->run($rule->fresh(), notify: true);
    }

    private function manual(DebugRule $rule): array
    {
        return app(RuleRunner::class)->run($rule->fresh(), notify: false);
    }

    private function failing(DebugRule $rule): void
    {
        $rule->update(['expected_rows' => 5]);
    }

    private function passing(DebugRule $rule): void
    {
        $rule->update(['expected_rows' => 1, 'sql_query' => 'select 1']);
    }

    // ---- 1. One alert per failing streak + recovery ------------------------

    public function test_alert_sent_once_per_failing_streak_then_recovery_mail(): void
    {
        Notification::fake();
        $rule = $this->rule();

        $this->scheduled($rule); // passing: nothing
        Notification::assertNothingSent();

        $this->failing($rule);
        $this->assertTrue($this->scheduled($rule)['notified']);
        $this->assertNotNull($rule->fresh()->failure_notified_at);

        $this->assertFalse($this->scheduled($rule)['notified']);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
        $this->assertSame(4, $rule->logs()->count()); // every run still logged

        $this->passing($rule);
        $this->assertTrue($this->scheduled($rule)['notified']);
        $this->assertNull($rule->fresh()->failure_notified_at);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleRecovered::class, 1);

        $this->failing($rule);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 2);

        Notification::assertSentOnDemand(DebugRuleRecovered::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'alerts@example.test'
                && $notification->toMail($notifiable)->subject === 'Debug Rule Recovered: Rule 1';
        });
    }

    // ---- 2. Query errors alert too ------------------------------------------

    public function test_query_error_sends_error_alert_and_recovers(): void
    {
        Notification::fake();
        $rule = $this->rule(['sql_query' => 'select * from missing_table']);

        $outcome = $this->scheduled($rule);
        $this->assertSame('failed', $outcome['status']);
        $this->assertNotNull($outcome['exception']);
        $this->assertTrue($outcome['notified']);

        Notification::assertSentOnDemand(DebugRuleAlert::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $mail->subject === 'Debug Rule Error: Rule 1'
                && $mail->introLines[0] === "A debug rule's query could not run."
                && isset($notification->result['exception']);
        });
        $this->assertSame('failed', $rule->fresh()->last_status);

        // Query fixed but data now mismatches: same failing streak, no new mail.
        $rule->update(['sql_query' => 'select 1', 'expected_rows' => 5]);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        $this->passing($rule);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleRecovered::class, 1);
    }

    public function test_data_failure_mail_keeps_failed_subject(): void
    {
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);
        $this->scheduled($rule);

        Notification::assertSentOnDemand(DebugRuleAlert::class, fn ($n, $c, $notifiable) => $n->toMail($notifiable)->subject === 'Debug Rule Failed: Rule 1');
    }

    // ---- Manual runs, suppress, notification level, mail errors -------------

    public function test_manual_runs_neither_send_nor_swallow_alerts(): void
    {
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);

        $this->manual($rule);
        Notification::assertNothingSent();
        $this->assertNull($rule->fresh()->failure_notified_at);

        $this->scheduled($rule); // first scheduled failure still alerts
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        $this->passing($rule);
        $this->manual($rule); // manual pass: no recovery mail, streak still open
        Notification::assertNotSentTo(new \Illuminate\Notifications\AnonymousNotifiable, DebugRuleRecovered::class);
        $this->assertNotNull($rule->fresh()->failure_notified_at);

        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleRecovered::class, 1);
    }

    public function test_suppressed_and_silent_rules_get_no_alerts(): void
    {
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5, 'suppress' => true]);

        $this->scheduled($rule);
        $rule->update(['suppress' => false, 'notification_level' => 'none']);
        $this->scheduled($rule);
        Notification::assertNothingSent();
        $this->assertNull($rule->fresh()->failure_notified_at);

        // Turned back on while still failing: the next run alerts.
        $rule->update(['notification_level' => 'urgent']);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        // Recovers while suppressed: streak closes without a mail.
        $rule->update(['suppress' => true]);
        $this->passing($rule);
        $this->scheduled($rule);
        Notification::assertSentOnDemandTimes(DebugRuleRecovered::class, 0);
        $this->assertNull($rule->fresh()->failure_notified_at);
    }

    public function test_mail_failure_is_retried_on_next_run(): void
    {
        $rule = $this->rule(['expected_rows' => 5]);

        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('smtp down'));
        $outcome = $this->scheduled($rule);
        $this->assertSame('failed', $outcome['status']);
        $this->assertFalse($outcome['notified']);
        $this->assertNull($rule->fresh()->failure_notified_at);
        $this->assertSame(1, $rule->logs()->count());

        Notification::fake();
        $this->assertTrue($this->scheduled($rule)['notified']);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
    }

    // ---- 4. Scheduler queues jobs ---------------------------------------------

    public function test_scheduler_queues_one_job_per_due_active_rule(): void
    {
        Queue::fake();
        $due = $this->rule(['name' => 'never run']);
        $notDue = $this->rule(['name' => 'ran recently', 'last_run_at' => now()->subMinute()]);
        $inactive = $this->rule(['name' => 'inactive', 'status' => 'inactive']);
        $overdue = $this->rule(['name' => 'overdue', 'last_run_at' => now()->subMinutes(20)]);

        $this->artisan('debug-monitor:run')->expectsOutput('Queued 2 due rule(s).')->assertSuccessful();

        Queue::assertPushed(RunScheduledDebugRuleJob::class, 2);
        $ids = collect(Queue::pushed(RunScheduledDebugRuleJob::class))->pluck('ruleId')->sort()->values()->all();
        $this->assertSame([$due->id, $overdue->id], $ids);
        Queue::assertNotPushed(RunDebugRuleJob::class, fn ($job) => get_class($job) === RunDebugRuleJob::class);
        $this->assertSame(0, \DB::table('debug_rule_logs')->count()); // nothing ran inline
    }

    public function test_scheduler_does_not_queue_a_rule_twice_while_its_job_is_pending(): void
    {
        Queue::fake();
        $this->rule();

        $this->artisan('debug-monitor:run')->assertSuccessful();
        $this->artisan('debug-monitor:run')->assertSuccessful();
        $this->artisan('debug-monitor:run')->assertSuccessful();

        Queue::assertPushed(RunScheduledDebugRuleJob::class, 1);
    }

    public function test_scheduler_runs_inline_with_sync_option_or_config(): void
    {
        Queue::fake();
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);

        $this->artisan('debug-monitor:run --sync')->expectsOutput('⚠️ Rule failed: Rule 1')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertNotNull($rule->fresh()->last_run_at);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        config(['debug-monitor.queue_runs' => false]);
        $rule->update(['last_run_at' => now()->subHour()]);
        $this->artisan('debug-monitor:run')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertSame(2, $rule->logs()->count());
    }

    public function test_scheduled_job_alerts_and_skips_inactive_or_not_due_rules(): void
    {
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);
        $runner = app(RuleRunner::class);

        (new RunScheduledDebugRuleJob($rule->id))->handle($runner);
        $this->assertSame(1, $rule->logs()->count());
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        // Just ran: a duplicate job that slipped through is a no-op.
        (new RunScheduledDebugRuleJob($rule->id))->handle($runner);
        $this->assertSame(1, $rule->logs()->count());

        $rule->update(['last_run_at' => now()->subHour(), 'status' => 'inactive']);
        (new RunScheduledDebugRuleJob($rule->id))->handle($runner);
        $this->assertSame(1, $rule->logs()->count());

        // Manual job still runs regardless and stays silent.
        (new RunDebugRuleJob($rule->id))->handle($runner);
        $this->assertSame(2, $rule->logs()->count());
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
    }

    public function test_scheduler_end_to_end_through_sync_queue(): void
    {
        config(['queue.default' => 'sync']);
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);

        $this->artisan('debug-monitor:run')->assertSuccessful();
        $this->assertSame('failed', $rule->fresh()->last_status);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        // Lock released after the job finished: the next due run queues again.
        $rule->update(['last_run_at' => now()->subHour()]);
        $this->artisan('debug-monitor:run')->assertSuccessful();
        $this->assertSame(2, $rule->logs()->count());
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
    }

    public function test_end_to_end_with_database_queue_and_database_cache(): void
    {
        // cache + jobs tables, as in SH10 (database cache and queue)
        $this->createCacheAndJobsTables();
        config(['cache.default' => 'database']);
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);

        $this->artisan('debug-monitor:run')->expectsOutput('Queued 1 due rule(s).')->assertSuccessful();
        $this->artisan('debug-monitor:run')->assertSuccessful(); // still pending: lock blocks a duplicate
        $this->assertSame(1, \DB::table('jobs')->count());
        $this->assertSame(1, \DB::table('cache_locks')->count());

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(0, \DB::table('jobs')->count());
        $this->assertSame(0, \DB::table('cache_locks')->count()); // released after the job ran
        $this->assertSame('failed', $rule->fresh()->last_status);
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        $rule->update(['last_run_at' => now()->subHour()]);
        $this->artisan('debug-monitor:run')->expectsOutput('Queued 1 due rule(s).');
        $this->assertSame(1, \DB::table('jobs')->count());
    }

    public function test_is_due(): void
    {
        $rule = $this->rule(['frequency_minutes' => 15]);
        $this->assertTrue($rule->isDue());
        $rule->last_run_at = now()->subMinutes(14);
        $this->assertFalse($rule->isDue());
        $rule->last_run_at = now()->subMinutes(15);
        $this->assertTrue($rule->isDue());
    }

    public function test_recovered_mail_failure_is_retried_on_next_passing_run(): void
    {
        Notification::fake();
        $rule = $this->rule(['expected_rows' => 5]);
        $this->scheduled($rule); // alerted
        $this->assertNotNull($rule->fresh()->failure_notified_at);

        $this->passing($rule);
        Notification::swap(\Mockery::mock(\Illuminate\Notifications\ChannelManager::class));
        Notification::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('smtp down'));
        $this->assertFalse($this->scheduled($rule)['notified']);
        $this->assertNotNull($rule->fresh()->failure_notified_at, 'streak stays open so recovery is retried');

        Notification::fake();
        $this->assertTrue($this->scheduled($rule)['notified']);
        Notification::assertSentOnDemandTimes(DebugRuleRecovered::class, 1);
        $this->assertNull($rule->fresh()->failure_notified_at);
    }

    public function test_alert_is_sent_immediately_not_queued(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        Queue::fake();
        $rule = $this->rule(['expected_rows' => 5]);
        $this->assertTrue($this->scheduled($rule)['notified']);
        Queue::assertNothingPushed(); // no separate mail job that could fail unseen
    }

    public function test_result_that_cannot_be_saved_still_records_the_run(): void
    {
        Notification::fake();
        // X'FF' is invalid UTF-8, so the sample can't be JSON-encoded.
        $rule = $this->rule(['sql_query' => "select X'FF' as b", 'expected_rows' => 5]);

        $outcome = $this->scheduled($rule);
        $this->assertSame('failed', $outcome['status']);

        $rule->refresh();
        $this->assertNotNull($rule->last_run_at);
        $this->assertSame('failed', $rule->last_status);
        $this->assertStringStartsWith('The run result could not be saved:', $rule->last_debug_log['exception']);
        $this->assertSame(1, $rule->logs()->count());
        $this->assertSame('failed', $rule->logs()->value('status'));
        Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);

        // A passing rule with an unsavable sample is still recorded as passing.
        $ok = $this->rule(['name' => 'ok', 'sql_query' => "select X'FF' as b", 'expected_rows' => 1]);
        $this->assertSame('ok', $this->manual($ok)['status']);
        $this->assertSame('ok', $ok->fresh()->last_status);

        // "Run now" no longer 500s on it.
        $this->post("/debug-monitor/rules/{$rule->id}/run")->assertRedirect();
    }

    public function test_is_due_compares_whole_minutes(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-25 10:15:00'));
        $rule = $this->rule(['frequency_minutes' => 15, 'last_run_at' => '2026-11-25 10:00:05']);
        $this->assertTrue($rule->fresh()->isDue(), 'due at the 10:15 tick');

        $rule->update(['frequency_minutes' => 1, 'last_run_at' => '2026-11-25 10:14:02']);
        $this->assertTrue($rule->fresh()->isDue(), 'a 1-minute rule is due at the next tick');

        $rule->update(['last_run_at' => '2026-11-25 10:15:00']);
        $this->assertFalse($rule->fresh()->isDue(\Carbon\Carbon::parse('2026-11-25 10:15:59')));

        $now = \Carbon\Carbon::parse('2026-11-25 10:15:30');
        $rule->fresh()->isDue($now);
        $this->assertSame('10:15:30', $now->format('H:i:s'), 'the passed-in time is not modified');
    }

    public function test_urgent_rules_send_high_priority_alerts(): void
    {
        config(['mail.default' => 'array']);
        $urgent = $this->rule(['name' => 'Urgent one', 'notification_level' => 'urgent', 'expected_rows' => 5]);
        $normal = $this->rule(['name' => 'Normal one', 'notification_level' => 'default', 'expected_rows' => 5]);

        $this->scheduled($urgent);
        $this->scheduled($normal);

        $sent = collect(app('mailer')->getSymfonyTransport()->messages())
            ->map(fn ($m) => $m->getOriginalMessage())
            ->keyBy(fn ($email) => $email->getSubject());

        $this->assertSame(['[Urgent] Debug Rule Failed: Urgent one', 'Debug Rule Failed: Normal one'], $sent->keys()->all());
        $this->assertSame(1, $sent['[Urgent] Debug Rule Failed: Urgent one']->getPriority());   // X-Priority: 1 (Highest)
        $this->assertSame(3, $sent['Debug Rule Failed: Normal one']->getPriority());           // 3 = normal

        // Recovery mails aren't urgent.
        $this->passing($urgent);
        $this->scheduled($urgent);
        $recovered = collect(app('mailer')->getSymfonyTransport()->messages())->last()->getOriginalMessage();
        $this->assertSame('Debug Rule Recovered: Urgent one', $recovered->getSubject());
        $this->assertSame(3, $recovered->getPriority());
    }

    public function test_works_in_apps_that_use_immutable_dates(): void
    {
        // e.g. Date::use(CarbonImmutable::class) in an AppServiceProvider: now() is immutable.
        \Illuminate\Support\Facades\Date::use(\Carbon\CarbonImmutable::class);

        try {
            Notification::fake();
            $rule = $this->rule(['expected_rows' => 5, 'last_run_at' => now()->subHour()]);
            $this->assertInstanceOf(\Carbon\CarbonImmutable::class, now());

            $this->assertTrue($rule->isDue(now()));
            $this->assertTrue($rule->isOverdue(now()));
            $this->get('/debug-monitor/dashboard')->assertOk()->assertSee('Rules are not running on schedule');

            $this->artisan('debug-monitor:run', ['--sync' => true])->assertSuccessful();
            $this->assertSame('failed', $rule->fresh()->last_status);
            $this->assertFalse($rule->fresh()->isDue(now()));
            Notification::assertSentOnDemandTimes(DebugRuleAlert::class, 1);
        } finally {
            \Illuminate\Support\Facades\Date::useDefault();
        }
    }
}
