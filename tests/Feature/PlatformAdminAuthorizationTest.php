<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_guest_cannot_write(): void
    {
        $response = $this->postJson('/api/platforms', ['name' => 'Sega Saturn']);

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_write(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/platforms', ['name' => 'Sega Saturn']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('platforms', ['name' => 'Sega Saturn']);
    }

    public function test_admin_can_create_and_update(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/platforms', ['name' => 'Sega Saturn']);
        $response->assertSuccessful();
        $platform = Platform::firstWhere('name', 'Sega Saturn');
        $this->assertNotNull($platform);

        $response = $this->actingAs($admin)->putJson("/api/platforms/{$platform->id}", ['name' => 'Sega Saturn (JP)']);
        $response->assertSuccessful();
        $this->assertDatabaseHas('platforms', ['id' => $platform->id, 'name' => 'Sega Saturn (JP)']);
    }

    public function test_admin_can_delete_platform_without_copies(): void
    {
        $platform = Platform::create(['name' => 'Sega Saturn']);

        $response = $this->actingAs($this->admin())->deleteJson("/api/platforms/{$platform->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('platforms', ['id' => $platform->id]);
    }

    public function test_admin_cannot_delete_platform_with_copies(): void
    {
        $platform = Platform::create(['name' => 'Sega Saturn']);
        $game = GameBase::create(['title' => 'Panzer Dragoon']);
        $owner = User::factory()->create();
        GameCopy::create([
            'user_id' => $owner->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/platforms/{$platform->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('platforms', ['id' => $platform->id]);
    }
}
