<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCopyUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCopy(User $user, Platform $platform): GameCopy
    {
        $game = GameBase::create(['title' => 'Chrono Trigger']);

        return GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);
    }

    public function test_owner_can_update_their_copy(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'notes' => 'Updated notes',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('game_copies', ['id' => $copy->id, 'notes' => 'Updated notes']);
    }

    public function test_non_owner_cannot_update_the_copy(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($owner, $platform);

        $response = $this->actingAs($otherUser)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'notes' => 'Hijacked',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('game_copies', ['id' => $copy->id, 'notes' => null]);
    }

    public function test_submitting_unchanged_parts_does_not_recreate_them(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $condition = Condition::create(['name' => 'Mint']);
        $copy = $this->makeCopy($user, $platform);
        $part = $copy->parts()->create(['type' => 'Cartridge', 'condition_id' => $condition->id, 'notes' => null]);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'parts' => [
                ['type' => 'Cartridge', 'condition_id' => $condition->id],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('copy_parts', ['id' => $part->id, 'type' => 'Cartridge']);
        $this->assertSame(1, $copy->parts()->count());
    }

    public function test_submitting_changed_parts_still_updates_them(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $mint = Condition::create(['name' => 'Mint']);
        $poor = Condition::create(['name' => 'Poor']);
        $copy = $this->makeCopy($user, $platform);
        $part = $copy->parts()->create(['type' => 'Cartridge', 'condition_id' => $mint->id, 'notes' => null]);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'parts' => [
                ['type' => 'Cartridge', 'condition_id' => $poor->id],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('copy_parts', ['id' => $part->id]);
        $this->assertDatabaseHas('copy_parts', ['game_copy_id' => $copy->id, 'condition_id' => $poor->id]);
    }
}
