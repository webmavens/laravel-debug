<?php

namespace Webmavens\DebugMonitor;

use Illuminate\Support\ServiceProvider;
use Webmavens\DebugMonitor\Console\Commands\RunDebugRulesCommand;
use Webmavens\DebugMonitor\Console\Commands\CleanDebugLogsCommand;
use Webmavens\DebugMonitor\Http\Middleware\AuthorizeDebugMonitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;

class DebugMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Gate::define('viewDebugMonitor', function ($user = null) {
            return app()->environment('local');
        });

        $router = $this->app['router'];
        $router->aliasMiddleware('debug-monitor.auth', AuthorizeDebugMonitor::class);

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'debug-monitor');

        $this->publishes([
            __DIR__.'/../stubs/DebugMonitorServiceProvider.stub' => app_path('Providers/DebugMonitorServiceProvider.php'),
        ], 'debug-monitor-provider');

        $this->publishes([
            __DIR__ . '/../config/debug-monitor.php' => config_path('debug-monitor.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/debug-monitor'),
        ], 'views');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunDebugRulesCommand::class,
                CleanDebugLogsCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('debug-monitor:run')->everyMinute();
                $schedule->command('debug-monitor:clean')->daily();
            });
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/debug-monitor.php', 'debug-monitor');
    }
}
