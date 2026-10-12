<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\ResendAdminOtpRequest;
use App\Http\Requests\VerifyAdminOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminOtpService;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    use ApiResponse;

    /**
     * Step 1 of 2FA sign-in: verify password, then email a one-time code.
     * No token is issued here — the session starts only after OTP verify.
     */
    public function login(AdminLoginRequest $request, AdminOtpService $otp, AuditService $audit): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! \Hash::check($request->validated('password'), $user->password)) {
            return $this->fail('Invalid email or password.', [], 401);
        }

        if ($user->status !== 'active') {
            return $this->fail('This account has been disabled. Contact support.', [], 403);
        }

        try {
            $expiresIn = $otp->issue($user);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Could not send the verification code. Please try again.', [], 500);
        }

        $audit->log('admin.otp_sent', User::class, $user->id, null, null, $user->id);

        return $this->ok([
            'requires_2fa' => true,
            'email' => $user->email,
            'email_masked' => AdminOtpService::maskEmail($user->email),
            'expires_in' => $expiresIn,
        ], 'Verification code sent to your email.');
    }

    /** Step 2 of 2FA sign-in: verify the emailed code, then issue the token. */
    public function verifyOtp(VerifyAdminOtpRequest $request, AdminOtpService $otp, AuditService $audit): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return $this->fail('Invalid or expired code. Please sign in again.', [], 422);
        }

        $result = $otp->verify($user, $request->validated('otp'));

        if ($result['status'] === 'ok') {
            $user->forceFill(['last_login_at' => now()])->save();
            $token = $user->createToken('admin-panel', ['admin'])->plainTextToken;

            $audit->log('admin.login', User::class, $user->id, null, null, $user->id);

            return $this->ok([
                'token' => $token,
                'token_type' => 'Bearer',
                'admin' => new UserResource($user),
            ], 'Logged in successfully.');
        }

        if ($result['status'] === 'expired') {
            return $this->fail('Code expired. Please sign in again to get a new code.', [], 422);
        }

        if ($result['status'] === 'locked') {
            $audit->log('admin.otp_locked', User::class, $user->id, null, null, $user->id);

            return $this->fail('Too many wrong attempts. Please sign in again to get a new code.', [], 403);
        }

        return $this->fail(
            "Wrong code. {$result['attempts_left']} ".($result['attempts_left'] === 1 ? 'attempt' : 'attempts').' left.',
            [],
            422
        );
    }

    /**
     * Resend the code for an active challenge. Always replies generically
     * so unknown emails can't be probed through this endpoint.
     */
    public function resendOtp(ResendAdminOtpRequest $request, AdminOtpService $otp): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || $user->status !== 'active' || ! \Cache::has($otp->challengeKey($user))) {
            return $this->ok([], 'If this email is registered, a new code is on its way.');
        }

        $wait = $otp->resendCooldown($user);
        if ($wait > 0) {
            return $this->fail(
                "Please wait {$wait} seconds before requesting a new code.",
                ['retry_after' => $wait],
                429
            );
        }

        try {
            $expiresIn = $otp->issue($user);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('Could not send the verification code. Please try again.', [], 500);
        }

        $otp->markResent($user);

        return $this->ok(['expires_in' => $expiresIn], 'A new code has been sent to your email.');
    }

    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        // Revoke all tokens: the admin panel is single-session by design, and
        // this avoids stale-token edge cases (e.g. token cache in long runners).
        $request->user()->tokens()->delete();

        if ($user) {
            $audit->log('admin.logout', \App\Models\User::class, $user->id, null, null, $user->id);
        }

        return $this->ok([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(['admin' => new UserResource($request->user())], 'Current admin user.');
    }
}
