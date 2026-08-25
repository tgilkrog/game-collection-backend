<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordChangedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_password_change_queues_confirmation_email(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/users/{$user->name}/password", [
            'current_password' => 'password',
            'password' => 'brandnewpassword',
            'password_confirmation' => 'brandnewpassword',
        ]);

        $response->assertStatus(204);

        Mail::assertQueued(PasswordChangedMail::class, fn ($mail) => $mail->user->is($user));
    }
}
