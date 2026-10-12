<?php

namespace Tests\Feature;

use App\Mail\AdminLoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@bookmydiagnose.in',
            'password' => bcrypt('admin123'),
        ], $overrides));
    }

    /** Step 1: valid password issues an OTP challenge — never a token. */
    public function test_admin_login_issues_otp_challenge(): void
    {
        Mail::fake();
        $this->admin();

        $res = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requires_2fa', true)
            ->assertJsonStructure(['data' => ['email', 'email_masked', 'expires_in']])
            ->assertJsonMissing(['token']);

        Mail::assertSent(AdminLoginOtp::class, fn ($mail) => $mail->hasTo('admin@bookmydiagnose.in'));
    }

    /** Step 1+2: correct OTP completes the login and returns a token. */
    public function test_admin_login_full_2fa_flow(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertOk();

        $code = null;
        Mail::assertSent(AdminLoginOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        $this->postJson('/api/v1/admin/verify-otp', [
            'email' => 'admin@bookmydiagnose.in',
            'otp' => $code,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'admin' => ['email', 'role']]]);
    }

    /** OTP cannot be reused after a successful verify. */
    public function test_otp_is_single_use(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertOk();

        $code = null;
        Mail::assertSent(AdminLoginOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/v1/admin/verify-otp', [
            'email' => 'admin@bookmydiagnose.in',
            'otp' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/admin/verify-otp', [
            'email' => 'admin@bookmydiagnose.in',
            'otp' => $code,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_verify_rejects_wrong_otp_with_attempts_left(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertOk();

        $this->postJson('/api/v1/admin/verify-otp', [
            'email' => 'admin@bookmydiagnose.in',
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Wrong code. 4 attempts left.');
    }

    public function test_verify_locks_after_five_wrong_attempts(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/admin/verify-otp', [
                'email' => 'admin@bookmydiagnose.in',
                'otp' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/admin/verify-otp', [
            'email' => 'admin@bookmydiagnose.in',
            'otp' => '000000',
        ])->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_resend_is_rate_limited(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertOk();

        // First resend goes through (fresh code, attempts reset).
        $this->postJson('/api/v1/admin/resend-otp', [
            'email' => 'admin@bookmydiagnose.in',
        ])->assertOk();

        // Immediate second resend hits the 60s cooldown.
        $this->postJson('/api/v1/admin/resend-otp', [
            'email' => 'admin@bookmydiagnose.in',
        ])->assertStatus(429)->assertJsonPath('success', false);
    }

    public function test_resend_never_reveals_unknown_email(): void
    {
        $this->postJson('/api/v1/admin/resend-otp', [
            'email' => 'nobody@example.com',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'wrongpass',
        ])->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_rejects_disabled_account(): void
    {
        $this->admin(['status' => 'disabled']);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@bookmydiagnose.in',
            'password' => 'admin123',
        ])->assertStatus(403);
    }

    public function test_admin_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/admin/me')->assertStatus(401);
    }

    public function test_me_and_logout_work_with_token(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('t')->plainTextToken;

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer $token"])
            ->assertOk()
            ->assertJsonPath('data.admin.email', 'admin@bookmydiagnose.in');

        $this->postJson('/api/v1/admin/logout', [], ['Authorization' => "Bearer $token"])
            ->assertOk();

        // Guards cache the resolved user inside one process; a real second
        // HTTP request would re-resolve from the DB. Forget guards to
        // simulate that here.
        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->getJson('/api/v1/admin/me', ['Authorization' => "Bearer $token"])
            ->assertStatus(401);
    }
}
