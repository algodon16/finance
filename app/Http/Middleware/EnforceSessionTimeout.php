<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Server-side 5-minute inactivity timeout for the whole web session.
 * Also sends no-store headers so protected pages are not reachable
 * via the browser Back button after logout/expiry.
 */
class EnforceSessionTimeout
{
    public const TIMEOUT_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $last = $request->session()->get('last_activity_at');

            if ($last && (time() - (int) $last) > self::TIMEOUT_SECONDS) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired. Please log in again.'], 419);
                }

                return redirect()->route('login')->withErrors([
                    'email' => 'Your session expired after 5 minutes of inactivity. Please log in again.',
                ]);
            }

            $request->session()->put('last_activity_at', time());
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
