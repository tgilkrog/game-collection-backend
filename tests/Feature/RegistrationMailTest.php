<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationMail;
use App\Mail\WelcomeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_queues_welcome_and_verification_emails(): void
    {
        Mail::fake();

        // register() ends with $request->session()->regenerate(), which throws unless the
        // request looks like it came from a stateful frontend (see EnsureFrontendRequestsAreStateful).
        $response = $this->withHeader('Referer', 'http://localhost')->postJson('/api/register', [
            'name' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        Mail::assertQueued(WelcomeEmail::class, fn ($mail) => $mail->user->email === 'newuser@example.com');
        Mail::assertQueued(EmailVerificationMail::class, fn ($mail) => $mail->user->email === 'newuser@example.com');
    }
}
