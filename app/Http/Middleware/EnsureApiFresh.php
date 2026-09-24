<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 5-minute activity timeout for stateless Sanctum API tokens.
 * The token's last_used_at is touched on every fresh request, so the
 * window slides with activity; idle tokens older than 5 minutes are
 * rejected with 401 and must re-authenticate (including OTP).
 */
class EnsureApiFresh
{
    public const TIMEOUT_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $last = $token->last_used_at ?? $token->created_at;
            if ($last && now()->diffInSeconds($last) > self::TIMEOUT_SECONDS) {
                $token->delete();
                return response()->json(['message' => 'Session expired. Please log in again.'], 401);
            }
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
