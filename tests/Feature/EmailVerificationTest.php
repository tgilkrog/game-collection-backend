<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signed_link_marks_email_verified_and_redirects_to_frontend(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($url);

        $response->assertRedirect(config('app.frontend_url').'/?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.com')]
        );

        $response = $this->get($url);

        $response->assertStatus(403);
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
