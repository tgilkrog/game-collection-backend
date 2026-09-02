<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\GameCopyReview;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCopyReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeCopy(User $user, Platform $platform): GameCopy
    {
        $game = GameBase::create(['title' => 'Chrono Trigger']);

        return GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);
    }

    public function test_creating_a_copy_always_produces_a_review(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $game = GameBase::create(['title' => 'Chrono Trigger']);

        $response = $this->actingAs($user)->postJson('/api/game-copies', [
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);

        $response->assertCreated();
        $copyId = $response->json('id');
        $this->assertSame(1, GameCopyReview::where('game_copy_id', $copyId)->count());
        $this->assertDatabaseHas('game_copy_reviews', ['game_copy_id' => $copyId, 'play_status' => 'backlog']);
    }

    public function test_deleting_a_copy_does_not_delete_its_review(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);
        $review = $copy->review()->create([
            'user_id' => $user->id,
            'game_base_id' => $copy->game_base_id,
            'play_status' => 'completed',
            'rating' => 5,
            'notes' => 'Loved it',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/game-copies/{$copy->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('game_copies', ['id' => $copy->id]);
        $this->assertDatabaseHas('game_copy_reviews', ['id' => $review->id, 'game_copy_id' => null, 'rating' => 5]);
    }

    public function test_owner_can_delete_a_review(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform);
        $review = $copy->review()->create(['user_id' => $user->id, 'game_base_id' => $copy->game_base_id, 'play_status' => 'backlog']);

        $response = $this->actingAs($user)->deleteJson("/api/game-copy-reviews/{$review->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('game_copy_reviews', ['id' => $review->id]);
    }

    public function test_non_owner_cannot_delete_a_review(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($owner, $platform);
        $review = $copy->review()->create(['user_id' => $owner->id, 'game_base_id' => $copy->game_base_id, 'play_status' => 'backlog']);

        $response = $this->actingAs($other)->deleteJson("/api/game-copy-reviews/{$review->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('game_copy_reviews', ['id' => $review->id]);
    }

    public function test_history_lists_only_the_callers_orphaned_reviews(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);

        $ownActiveCopy = $this->makeCopy($user, $platform);
        $ownActiveCopy->review()->create(['user_id' => $user->id, 'game_base_id' => $ownActiveCopy->game_base_id, 'play_status' => 'playing']);

        $ownDeletedCopy = $this->makeCopy($user, $platform);
        $orphanedReview = $ownDeletedCopy->review()->create(['user_id' => $user->id, 'game_base_id' => $ownDeletedCopy->game_base_id, 'play_status' => 'completed', 'rating' => 4]);
        $ownDeletedCopy->delete();

        $otherDeletedCopy = $this->makeCopy($other, $platform);
        $otherDeletedCopy->review()->create(['user_id' => $other->id, 'game_base_id' => $otherDeletedCopy->game_base_id, 'play_status' => 'completed']);
        $otherDeletedCopy->delete();

        $response = $this->actingAs($user)->getJson('/api/game-copy-reviews/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $orphanedReview->id);
    }

    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/game-copy-reviews/history');

        $response->assertStatus(401);
    }
}
