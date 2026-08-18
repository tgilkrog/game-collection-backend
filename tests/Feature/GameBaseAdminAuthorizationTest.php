<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBaseAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function payload(): array
    {
        return [
            'title' => 'Custom Game',
            'release_year' => 1998,
        ];
    }

    public function test_guest_cannot_create_a_game_base(): void
    {
        $response = $this->postJson('/api/game-base', $this->payload());

        $response->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_create_a_game_base(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/game-base', $this->payload());

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_game_base(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/game-base', $this->payload());

        $response->assertOk();
        $this->assertDatabaseHas('game_bases', ['title' => 'Custom Game']);
    }

    public function test_non_admin_user_cannot_update_a_game_base(): void
    {
        $game = GameBase::create($this->payload());
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/game-base/{$game->id}", [
            'title' => 'Renamed',
            'release_year' => 1999,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_a_game_base(): void
    {
        $game = GameBase::create($this->payload());

        $response = $this->actingAs($this->admin())->putJson("/api/game-base/{$game->id}", [
            'title' => 'Renamed',
            'release_year' => 1999,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('game_bases', ['id' => $game->id, 'title' => 'Renamed']);
    }

    public function test_non_admin_user_cannot_delete_a_game_base(): void
    {
        $game = GameBase::create($this->payload());
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/game-base/{$game->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('game_bases', ['id' => $game->id]);
    }

    public function test_admin_can_delete_a_game_base(): void
    {
        $game = GameBase::create($this->payload());

        $response = $this->actingAs($this->admin())->deleteJson("/api/game-base/{$game->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('game_bases', ['id' => $game->id]);
    }
}
