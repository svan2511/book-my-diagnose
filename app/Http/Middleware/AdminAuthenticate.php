<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single admin gate: Sanctum authentication + active staff account.
 * Returns the standard JSON envelope directly instead of throwing, so the
 * response contract never depends on guest-redirect internals (dev-prd §5-§7).
 */
class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => (object) [],
            ], 401);
        }

        Auth::shouldUse('sanctum');

        if ($user->status !== 'active' || ! in_array($user->role, ['super_admin', 'admin', 'lab_staff', 'support_staff'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
