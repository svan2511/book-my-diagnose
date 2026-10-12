<?php

namespace App\Services;

use App\Mail\AdminLoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Email-OTP second factor for admin sign-in.
 *
 * Only the SHA-256 hash of the code ever touches the cache; the plain
 * code exists only in the email that was sent. Brute force is capped by
 * a per-user attempt limiter, resends by a cooldown limiter.
 */
class AdminOtpService
{
    public const CODE_LENGTH = 6;

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function challengeKey(User $user): string
    {
        return "admin:otp:challenge:{$user->getKey()}";
    }

    private function verifyKey(User $user): string
    {
        return "admin:otp:verify:{$user->getKey()}";
    }

    private function resendKey(User $user): string
    {
        return "admin:otp:resend:{$user->getKey()}";
    }

    /**
     * Generate a fresh code, store only its hash and email it.
     *
     * @return int seconds until the code expires
     *
     * @throws \Throwable when the mailer fails — caller maps to a 5xx
     */
    public function issue(User $user): int
    {
        $code = (string) random_int(
            10 ** (self::CODE_LENGTH - 1),
            (10 ** self::CODE_LENGTH) - 1
        );

        Cache::put(
            $this->challengeKey($user),
            hash('sha256', $code),
            now()->addMinutes(self::TTL_MINUTES)
        );
        RateLimiter::clear($this->verifyKey($user));

        Mail::to($user->email)->send(new AdminLoginOtp($user, $code, self::TTL_MINUTES));

        return self::TTL_MINUTES * 60;
    }

    /**
     * @return array{status: 'ok'|'expired'|'invalid'|'locked', attempts_left: int}
     */
    public function verify(User $user, string $code): array
    {
        if (RateLimiter::tooManyAttempts($this->verifyKey($user), self::MAX_ATTEMPTS)) {
            $this->clear($user);

            return ['status' => 'locked', 'attempts_left' => 0];
        }

        $expected = Cache::get($this->challengeKey($user));
        if (! is_string($expected)) {
            return ['status' => 'expired', 'attempts_left' => 0];
        }

        if (! hash_equals($expected, hash('sha256', trim($code)))) {
            RateLimiter::hit($this->verifyKey($user), self::TTL_MINUTES * 60);
            $left = RateLimiter::remaining($this->verifyKey($user), self::MAX_ATTEMPTS);

            if ($left <= 0) {
                $this->clear($user);

                return ['status' => 'locked', 'attempts_left' => 0];
            }

            return ['status' => 'invalid', 'attempts_left' => $left];
        }

        $this->clear($user);
        RateLimiter::clear($this->verifyKey($user));

        return ['status' => 'ok', 'attempts_left' => self::MAX_ATTEMPTS];
    }

    /** Seconds until a resend is allowed (0 = allowed right now). */
    public function resendCooldown(User $user): int
    {
        return RateLimiter::availableIn($this->resendKey($user));
    }

    public function markResent(User $user): void
    {
        RateLimiter::hit($this->resendKey($user), self::RESEND_COOLDOWN_SECONDS);
    }

    public function clear(User $user): void
    {
        Cache::forget($this->challengeKey($user));
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            return '***';
        }

        return mb_substr($parts[0], 0, 1).'***@'.$parts[1];
    }
}
