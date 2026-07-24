<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.ssh_bridge.internal_secret');

        if (! $secret || $request->header('X-Internal-Secret') !== $secret) {
            abort(403);
        }

        return $next($request);
    }
}
