<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks fully-authenticated access until the email/phone OTP challenge
 * stored in the session has been completed.
 */
class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && $request->session()->get('otp.verified') !== true) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Please complete the OTP verification first.',
            ]);
        }

        return $next($request);
    }
}
