<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_guest_cannot_access_admin_user_routes(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->putJson("/api/admin/users/{$user->name}/promote")->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_admin_user_routes(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($actor)->getJson('/api/admin/users');
        $response->assertForbidden();

        $response = $this->actingAs($actor)->putJson("/api/admin/users/{$target->name}/promote");
        $response->assertForbidden();
    }

    public function test_admin_can_list_users_with_admin_fields(): void
    {
        $target = User::factory()->create(['email' => 'target@example.com']);

        $response = $this->actingAs($this->admin())->getJson('/api/admin/users');

        $response->assertOk();
        $response->assertJsonFragment(['email' => 'target@example.com']);
        $item = collect($response->json('data'))->firstWhere('id', $target->id);
        $this->assertArrayHasKey('is_admin', $item);
        $this->assertArrayHasKey('is_banned', $item);
        $this->assertArrayHasKey('created_at', $item);
    }

    public function test_admin_can_promote_and_demote_another_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$target->name}/promote");
        $response->assertOk();
        $this->assertTrue($target->fresh()->is_admin);

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$target->name}/demote");
        $response->assertOk();
        $this->assertFalse($target->fresh()->is_admin);
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$admin->name}/demote");

        $response->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_cannot_ban_self(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$admin->name}/ban");

        $response->assertStatus(422);
        $this->assertFalse($admin->fresh()->is_banned);
    }

    public function test_admin_can_ban_and_unban_another_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$target->name}/ban");
        $response->assertOk();
        $this->assertTrue($target->fresh()->is_banned);

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$target->name}/unban");
        $response->assertOk();
        $this->assertFalse($target->fresh()->is_banned);
    }

    public function test_banned_user_is_locked_out_of_authenticated_requests(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_banned' => true])->save();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertStatus(403);
        $response->assertHeader('X-Account-Banned', 'true');
    }

    public function test_banned_user_can_still_log_out(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_banned' => true])->save();

        $response = $this->actingAs($user)
            ->withHeader('Referer', 'http://localhost')
            ->postJson('/api/logout');

        $response->assertNoContent();
    }

    public function test_login_rejects_a_banned_user_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        $user->forceFill(['is_banned' => true])->save();

        $response = $this->withHeader('Referer', 'http://localhost')->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();
    }
}
