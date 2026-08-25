<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_gives_same_response_for_existing_and_unknown_email(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertStatus(200);
        $unknown->assertStatus(200);
        $this->assertSame($known->json('message'), $unknown->json('message'));

        Mail::assertQueued(PasswordResetMail::class, fn ($mail) => $mail->user->email === 'known@example.com');
        Mail::assertNotQueued(PasswordResetMail::class, fn ($mail) => $mail->user->email === 'nobody@example.com');
    }

    public function test_reset_with_valid_token_changes_password_and_queues_confirmation(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        // login() ends with $request->session()->regenerate(), which throws unless the
        // request looks like it came from a stateful frontend (see EnsureFrontendRequestsAreStateful).
        $login = $this->withHeader('Referer', 'http://localhost')->postJson('/api/login', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
        ]);
        $login->assertStatus(200);

        Mail::assertQueued(PasswordChangedMail::class, fn ($mail) => $mail->user->email === 'reset@example.com');
    }

    public function test_reset_with_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset2@example.com']);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'reset2@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);

        $login = $this->postJson('/api/login', [
            'email' => 'reset2@example.com',
            'password' => 'newpassword123',
        ]);
        $login->assertStatus(401);
    }
}
