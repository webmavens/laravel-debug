<?php

namespace Webmavens\DebugMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;

class AuthorizeDebugMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::denies('viewDebugMonitor', [$request->user()])) {
            abort(403);
        }

        return $next($request);
    }
}
