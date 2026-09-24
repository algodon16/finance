<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (auth()->check()) {
            return $this->redirectToDashboard();
        }
        return view('auth.login');
    }

    public function login(Request $request, OtpService $otp)
    {
        $credentials = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|max:255',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            try {
                AuditService::log('failed_login', 'auth', null, null, ['email' => $credentials['email']]);
            } catch (\Throwable $e) {
                // Never leak logging failures to the login form.
            }

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        // Credentials OK — do NOT authenticate yet. Start the OTP challenge.
        Auth::logout();
        $request->session()->regenerate();

        $result = $otp->issue($user, 'email');
        if (isset($result['cooldown'])) {
            return back()->withErrors([
                'email' => 'Please wait '.$result['cooldown'].' seconds before trying again.',
            ])->onlyInput('email');
        }

        try {
            Mail::to($user->email)->send(new OtpMail($user->name, $result['code']));
        } catch (\Throwable $e) {
            return back()->withErrors([
                'email' => 'Unable to send the verification code. Please try again.',
            ])->onlyInput('email');
        }
        unset($result['code']);

        $request->session()->put('otp.user_id', $user->id);
        $request->session()->put('otp.channel', 'email');
        $request->session()->put('otp.verified', false);

        try {
            AuditService::log('login', 'auth', (string) $user->id);
        } catch (\Throwable $e) {
        }

        return redirect()->route('otp.email');
    }

    public function logout(Request $request)
    {
        try {
            AuditService::log('logout', 'auth', auth()->id() ? (string) auth()->id() : null);
        } catch (\Throwable $e) {
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    protected function redirectToDashboard()
    {
        $user = auth()->user();

        if (!$user || !$user->is_active) {
            Auth::logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        return match ($user->role) {
            'student' => redirect()->route('student.dashboard'),
            'cashier' => redirect()->route('cashier.dashboard'),
            'accountant' => redirect()->route('accountant.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            default => redirect()->route('login'),
        };
    }
}
