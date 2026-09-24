<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    protected function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('otp.user_id');
        if (! $id) return null;
        return User::find($id);
    }

    protected function clearPending(Request $request): void
    {
        $request->session()->forget(['otp.user_id', 'otp.channel', 'otp.verified']);
    }

    public function showEmail(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        $request->session()->put('otp.channel', 'email');
        $record = $otp->activeRecord($user, 'email');
        if (! $record) {
            $this->clearPending($request);
            return redirect()->route('login')->withErrors(['email' => 'Verification code expired. Please log in again.']);
        }

        return view('auth.otp-email', [
            'maskedEmail' => OtpService::maskEmail($user->email),
            'expiresIn' => $otp->remainingSeconds($record),
            'cooldown' => $otp->cooldownSeconds($user, 'email'),
            'attemptsLeft' => $otp->attemptsLeft($record),
            'hasPhone' => ! empty($user->phone_number),
        ]);
    }

    public function verifyEmail(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        $data = $request->validate(['code' => 'required|string|size:6|regex:/^[0-9]{6}$/']);

        $key = 'otp-verify:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['code' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $result = $otp->verify($user, 'email', $data['code']);

        if (! $result['ok']) {
            RateLimiter::hit($key, 120);
            return match ($result['reason']) {
                'expired' => redirect()->route('login')->withErrors(['email' => 'Verification code expired. Please log in again.']),
                'locked' => redirect()->route('login')->withErrors(['email' => 'Too many incorrect attempts. Please log in again to request a new code.']),
                default => back()->withErrors(['code' => 'Incorrect verification code.']),
            };
        }

        RateLimiter::clear($key);
        $this->completeLogin($request, $user, 'email');

        return $this->redirectToDashboard($user);
    }

    public function resendEmail(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        $result = $otp->issue($user, 'email');
        if (isset($result['cooldown'])) {
            return back()->withErrors(['code' => 'Please wait '.$result['cooldown'].' seconds before requesting a new code.']);
        }

        try {
            Mail::to($user->email)->send(new OtpMail($user->name, $result['code']));
        } catch (\Throwable $e) {
            return back()->withErrors(['code' => 'Unable to send the verification email. Please try again.']);
        }
        unset($result['code']);

        try {
            AuditService::log('otp_resend', 'auth', (string) $user->id);
        } catch (\Throwable $e) {
        }

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    public function sendPhone(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        if (empty($user->phone_number)) {
            return back()->withErrors(['code' => 'No phone number is registered for this account. Please contact the administrator.']);
        }

        $result = $otp->issue($user, 'phone');
        if (isset($result['cooldown'])) {
            return back()->withErrors(['code' => 'Please wait '.$result['cooldown'].' seconds before requesting a new code.']);
        }

        // Delivery via configured SMS provider; default driver logs metadata only (never the code).
        $otp->queueSms($user, OtpService::maskPhone($user->phone_number));
        unset($result['code']);

        $request->session()->put('otp.channel', 'phone');

        try {
            AuditService::log('otp_phone_send', 'auth', (string) $user->id);
        } catch (\Throwable $e) {
        }

        return redirect()->route('otp.phone');
    }

    public function showPhone(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user || empty($user->phone_number)) return redirect()->route('login');

        $request->session()->put('otp.channel', 'phone');
        $record = $otp->activeRecord($user, 'phone');
        if (! $record) {
            $this->clearPending($request);
            return redirect()->route('login')->withErrors(['email' => 'Verification code expired. Please log in again.']);
        }

        return view('auth.otp-phone', [
            'maskedPhone' => OtpService::maskPhone($user->phone_number),
            'expiresIn' => $otp->remainingSeconds($record),
            'cooldown' => $otp->cooldownSeconds($user, 'phone'),
            'attemptsLeft' => $otp->attemptsLeft($record),
        ]);
    }

    public function verifyPhone(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        $data = $request->validate(['code' => 'required|string|size:6|regex:/^[0-9]{6}$/']);

        $key = 'otp-verify-phone:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['code' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $result = $otp->verify($user, 'phone', $data['code']);

        if (! $result['ok']) {
            RateLimiter::hit($key, 120);
            return match ($result['reason']) {
                'expired' => redirect()->route('login')->withErrors(['email' => 'Verification code expired. Please log in again.']),
                'locked' => redirect()->route('login')->withErrors(['email' => 'Too many incorrect attempts. Please log in again to request a new code.']),
                default => back()->withErrors(['code' => 'Incorrect verification code.']),
            };
        }

        RateLimiter::clear($key);
        if (empty($user->phone_verified_at)) {
            $user->update(['phone_verified_at' => now()]);
        }
        $this->completeLogin($request, $user, 'phone');

        return $this->redirectToDashboard($user);
    }

    public function resendPhone(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        $result = $otp->issue($user, 'phone');
        if (isset($result['cooldown'])) {
            return back()->withErrors(['code' => 'Please wait '.$result['cooldown'].' seconds before requesting a new code.']);
        }

        $otp->queueSms($user, OtpService::maskPhone($user->phone_number));
        unset($result['code']);

        return back()->with('status', 'A new verification code has been sent to your phone.');
    }

    public function backToEmail(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);
        if (! $user) return redirect()->route('login');

        if (! $otp->activeRecord($user, 'email')) {
            $result = $otp->issue($user, 'email');
            if (! isset($result['cooldown'])) {
                try {
                    Mail::to($user->email)->send(new OtpMail($user->name, $result['code']));
                } catch (\Throwable $e) {
                    return back()->withErrors(['code' => 'Unable to send the verification email. Please try again.']);
                }
                unset($result['code']);
            }
        }

        $request->session()->put('otp.channel', 'email');

        return redirect()->route('otp.email');
    }

    protected function completeLogin(Request $request, User $user, string $channel): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('otp.verified', true);
        $request->session()->put('last_activity_at', time());

        try {
            AuditService::log('otp_verified', 'auth', (string) $user->id, null, ['channel' => $channel]);
        } catch (\Throwable $e) {
        }
    }

    protected function redirectToDashboard(User $user)
    {
        if (! $user->is_active) {
            Auth::logout();
            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
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
