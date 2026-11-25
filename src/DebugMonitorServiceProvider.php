<?php

namespace Webmavens\DebugMonitor;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Webmavens\DebugMonitor\Console\Commands\CleanDebugLogsCommand;
use Webmavens\DebugMonitor\Console\Commands\ExportDebugRulesCommand;
use Webmavens\DebugMonitor\Console\Commands\RunDebugRulesCommand;
use Webmavens\DebugMonitor\Console\Commands\SyncDebugRulesCommand;
use Webmavens\DebugMonitor\Http\Middleware\AuthorizeDebugMonitor;
use Webmavens\DebugMonitor\View\Composers\RecentFailedRulesComposer;
use Webmavens\DebugMonitor\View\Composers\SchedulerHealthComposer;

class DebugMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->gate();

        View::composer(
            'debug-monitor::layouts.sidebar',
            RecentFailedRulesComposer::class
        );

        View::composer(
            'debug-monitor::layouts.app',
            SchedulerHealthComposer::class
        );

        $router = $this->app['router'];
        $router->aliasMiddleware('debug-monitor.auth', AuthorizeDebugMonitor::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'debug-monitor');

        $this->publishes([
            __DIR__.'/../config/debug-monitor.php' => config_path('debug-monitor.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/debug-monitor'),
        ], 'views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../stubs/debug-monitor-rules' => base_path('debug-monitor-rules'),
        ], 'debug-monitor-rules');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunDebugRulesCommand::class,
                CleanDebugLogsCommand::class,
                SyncDebugRulesCommand::class,
                ExportDebugRulesCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                // withoutOverlapping stops a slow run from stacking on top of the
                // previous minute's run and piling load onto the database.
                $schedule->command('debug-monitor:run')
                    ->everyMinute()
                    ->withoutOverlapping();
                $schedule->command('debug-monitor:clean')->daily();
            });
        }
    }

    protected function gate(): void
    {
        Gate::define('viewDebugMonitor', function ($authenticatedUser = null, $requestUser = null): bool {
            if ((bool) config('debug-monitor.allow_in_local', true) && app()->environment('local')) {
                return true;
            }

            $allowedEmails = config('debug-monitor.allowed_emails', []);
            $user = $requestUser ?? $authenticatedUser;
            $userEmail = data_get($user, 'email');

            return is_string($userEmail) && in_array($userEmail, $allowedEmails, true);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/debug-monitor.php', 'debug-monitor');
    }
}
