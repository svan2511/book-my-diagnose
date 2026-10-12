<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This is a pure JSON API (no Blade, no redirects). Force JSON expectations
 * for every /api/* request so framework defaults (guest redirects, HTML
 * error pages) can never leak out to API clients that omit the
 * Accept header — e.g. browsers, webhooks, share links.
 */
class ForceApiJson
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && ! $request->headers->has('Accept')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
