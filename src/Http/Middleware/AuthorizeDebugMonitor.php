<?php

namespace Webmavens\DebugMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDebugMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $env = config('app.env');
        $key = config('debug-monitor.auth_key');

        if ($env === 'local' || session('debug_monitor_authenticated')) {
            return $next($request);
        }

        $providedKey = $request->query('key') ?? $request->header('X-Debug-Monitor-Key');

        if ($key && $key === $providedKey) {
            session(['debug_monitor_authenticated' => true]);

            return $next($request);
        }

        abort(403, 'Unauthorized access to Debug Monitor.');
    }
}