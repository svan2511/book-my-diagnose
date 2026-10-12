<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation ID for every API request (dev-prd §48).
 * Accepts an incoming X-Request-ID or generates one, and echoes it back.
 */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-ID') ?: (string) Str::uuid();
        $request->headers->set('X-Request-ID', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
