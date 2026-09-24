<?php

namespace App\Services;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Server-side OTP manager.
 *
 * - 6-digit codes generated with random_int().
 * - Only the hash is persisted (never plain text).
 * - 5-minute expiry, 60-second resend cooldown, 5 attempt limit.
 * - Previous active OTPs for the same user+channel are invalidated on issue.
 */
class OtpService
{
    public const EXPIRY_MINUTES = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array{record: LoginOtp, code: string}|array{cooldown: int}
     */
    public function issue(User $user, string $channel): array
    {
        $channel = $channel === 'phone' ? 'phone' : 'email';

        $latest = LoginOtp::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($latest && $latest->last_sent_at && now()->diffInSeconds($latest->last_sent_at) < self::RESEND_COOLDOWN_SECONDS) {
            return ['cooldown' => self::RESEND_COOLDOWN_SECONDS - now()->diffInSeconds($latest->last_sent_at)];
        }

        // Invalidate any other active OTPs for this user+channel.
        LoginOtp::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        $record = LoginOtp::create([
            'user_id' => $user->id,
            'channel' => $channel,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        return ['record' => $record, 'code' => $code];
    }

    public function activeRecord(User $user, string $channel): ?LoginOtp
    {
        $channel = $channel === 'phone' ? 'phone' : 'email';

        return LoginOtp::where('user_id', $user->id)
            ->where('channel', $channel)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{ok: bool, reason?: string}
     */
    public function verify(User $user, string $channel, string $code): array
    {
        $record = $this->activeRecord($user, $channel);

        if (! $record) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ($record->isExpired()) {
            $record->update(['consumed_at' => now()]);
            return ['ok' => false, 'reason' => 'expired'];
        }
        if ($record->attempts >= self::MAX_ATTEMPTS) {
            $record->update(['consumed_at' => now()]);
            return ['ok' => false, 'reason' => 'locked'];
        }
        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            if ($record->fresh()->attempts >= self::MAX_ATTEMPTS) {
                $record->update(['consumed_at' => now()]);
                return ['ok' => false, 'reason' => 'locked'];
            }
            return ['ok' => false, 'reason' => 'mismatch'];
        }

        $record->update(['consumed_at' => now()]);

        return ['ok' => true];
    }

    public function remainingSeconds(?LoginOtp $record): int
    {
        if (! $record || $record->isConsumed()) return 0;
        $diff = now()->diffInSeconds($record->expires_at, false);
        return max(0, (int) $diff);
    }

    public function cooldownSeconds(User $user, string $channel): int
    {
        $record = LoginOtp::where('user_id', $user->id)
            ->where('channel', $channel === 'phone' ? 'phone' : 'email')
            ->latest('id')
            ->first();

        if (! $record || ! $record->last_sent_at) return 0;
        $elapsed = now()->diffInSeconds($record->last_sent_at);
        return $elapsed >= self::RESEND_COOLDOWN_SECONDS ? 0 : self::RESEND_COOLDOWN_SECONDS - $elapsed;
    }

    public function attemptsLeft(?LoginOtp $record): int
    {
        if (! $record) return 0;
        return max(0, self::MAX_ATTEMPTS - (int) $record->attempts);
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        [$local, $domain] = $parts;
        $first = mb_substr($local, 0, 1);
        return $first . '***@' . $domain;
    }

    public static function maskPhone(?string $phone): string
    {
        if (! $phone) return '***';
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 7) return '***';
        $country = strlen($digits) > 10 ? '+' . substr($digits, 0, strlen($digits) - 10) . ' ' : '';
        return $country . substr($digits, -10, 3) . ' *** ' . substr($digits, -4);
    }

    /**
     * SMS delivery hook. Default driver only logs metadata (never the code).
     * Configure a real provider via SMS_* env vars; keep credentials in .env.
     */
    public function queueSms(User $user, string $maskedPhone): void
    {
        Log::info('OTP SMS queued', [
            'user_id' => $user->id,
            'masked_phone' => $maskedPhone,
            'driver' => env('SMS_DRIVER', 'log'),
        ]);
    }
}
