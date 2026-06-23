<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmavens\DebugMonitor\Http\Middleware\AuthorizeDebugMonitor;

uses(Tests\TestCase::class);

test('debug monitor middleware allows access in local environment', function () {
    $this->app['env'] = 'local';

    $middleware = $this->app->make(AuthorizeDebugMonitor::class);

    $request = Request::create('/debug-monitor/rules', 'GET');

    $response = $middleware->handle(
        $request,
        fn (): Response => new Response('ok')
    );

    expect($response->getStatusCode())->toBe(200);
});

test('debug monitor middleware allows configured emails outside local', function () {
    $this->app['env'] = 'production';
    config(['debug-monitor.allowed_emails' => ['admin@example.com']]);

    $user = new class ('admin@example.com') {
        public function __construct(public string $email)
        {
        }
    };

    $middleware = $this->app->make(AuthorizeDebugMonitor::class);
    $request = Request::create('/debug-monitor/rules', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $middleware->handle(
        $request,
        fn (): Response => new Response('ok')
    );

    expect($response->getStatusCode())->toBe(200);
});

test('debug monitor middleware blocks non-configured emails outside local', function () {
    $this->app['env'] = 'production';
    config(['debug-monitor.allowed_emails' => ['admin@example.com']]);

    $user = new class ('other@example.com') {
        public function __construct(public string $email)
        {
        }
    };

    $middleware = $this->app->make(AuthorizeDebugMonitor::class);
    $request = Request::create('/debug-monitor/rules', 'GET');
    $request->setUserResolver(fn () => $user);

    expect(fn () => $middleware->handle(
        $request,
        fn (): Response => new Response('ok')
    ))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});
