<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Password gate for Financial Reporting and Compliance.
 *
 * Locked visitors still receive the page shell (rendered blurred with the
 * Secure Access modal), but JSON/API consumers get 403. Unlock state lives
 * only in the server session and dies with logout/session timeout.
 */
class EnsureReportsUnlocked
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->session()->get('reports.unlocked') === true) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Reports access requires password verification.'], 403);
        }

        return $next($request);
    }

    public static function isLocked(): bool
    {
        if (! auth()->check()) return true;
        if (! request()->routeIs('admin.reports.*')) return false;
        return session('reports.unlocked') !== true;
    }
}
