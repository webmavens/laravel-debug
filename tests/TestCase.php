<?php

namespace Webmavens\DebugMonitor\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Webmavens\DebugMonitor\DebugMonitorServiceProvider;
use Webmavens\DebugMonitor\Models\DebugRule;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [DebugMonitorServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['env'] = 'local'; // the access gate lets everyone in locally
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests post forms directly. The CSRF middleware's class name differs
        // between Laravel versions, so switch off whichever one exists.
        foreach ([
            'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
            'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
        ] as $csrf) {
            if (class_exists($csrf)) {
                $this->withoutMiddleware($csrf);
            }
        }
    }

    protected function makeRule(array $overrides = []): DebugRule
    {
        return DebugRule::create(array_merge([
            'name' => 'Rule',
            'sql_query' => 'select 1',
            'frequency_minutes' => 15,
            'importance_level' => 'medium',
            'expected_rows_operator' => '=',
            'expected_rows' => 1,
            'notification_level' => 'none',
            'status' => 'active',
        ], $overrides));
    }

    /** Create the cache + jobs tables an app would have, for database cache/queue tests. */
    protected function createCacheAndJobsTables(): void
    {
        $schema = $this->app['db']->connection()->getSchemaBuilder();

        $schema->create('cache', function ($table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        $schema->create('cache_locks', function ($table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
        if (! $schema->hasTable('jobs')) {
            $schema->create('jobs', function ($table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }
}
