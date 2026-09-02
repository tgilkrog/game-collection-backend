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
        $this->assertDatabaseHas('game_copy_reviews', ['game_copy_id' => $copy->id, 'notes' => 'Updated notes']);
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
        $this->assertDatabaseMissing('game_copy_reviews', ['game_copy_id' => $copy->id, 'notes' => 'Hijacked']);
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

    public function test_owner_can_update_play_status_rating_and_hours_played(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'play_status' => 'playing',
            'rating' => 4,
            'hours_played' => 12.5,
        ]);

        $response->assertOk();
        $response->assertJsonPath('play_status', 'playing');
        $response->assertJsonPath('rating', 4);
        $this->assertDatabaseHas('game_copy_reviews', [
            'game_copy_id' => $copy->id,
            'play_status' => 'playing',
            'rating' => 4,
            'hours_played' => 12.5,
        ]);
    }

    public function test_updating_without_review_fields_preserves_existing_review(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);
        $copy->review()->create(['user_id' => $user->id, 'game_base_id' => $copy->game_base_id, 'play_status' => 'completed', 'rating' => 5]);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'region' => 'EU',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('game_copy_reviews', [
            'game_copy_id' => $copy->id,
            'play_status' => 'completed',
            'rating' => 5,
        ]);
    }

    public function test_invalid_rating_is_rejected(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'rating' => 6,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_play_status_is_rejected(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'play_status' => 'shelved',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_update_playthrough_count_and_would_replay_recommend(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'playthrough_count' => 2,
            'would_replay' => true,
            'would_recommend' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('playthrough_count', 2);
        $response->assertJsonPath('would_replay', true);
        $response->assertJsonPath('would_recommend', false);
        $this->assertDatabaseHas('game_copy_reviews', [
            'game_copy_id' => $copy->id,
            'playthrough_count' => 2,
            'would_replay' => true,
            'would_recommend' => false,
        ]);
    }

    public function test_updating_without_playthrough_fields_preserves_existing_values(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);
        $copy->review()->create([
            'user_id' => $user->id,
            'game_base_id' => $copy->game_base_id,
            'playthrough_count' => 3,
            'would_replay' => true,
            'would_recommend' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'notes' => 'Just updating notes',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('game_copy_reviews', [
            'game_copy_id' => $copy->id,
            'playthrough_count' => 3,
            'would_replay' => true,
            'would_recommend' => true,
            'notes' => 'Just updating notes',
        ]);
    }

    public function test_invalid_playthrough_count_is_rejected(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'playthrough_count' => -1,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_would_replay_is_rejected(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);

        $response = $this->actingAs($user)->putJson("/api/game-copies/{$copy->id}", [
            'platform_id' => $platform->id,
            'would_replay' => 'maybe',
        ]);

        $response->assertStatus(422);
    }
}
