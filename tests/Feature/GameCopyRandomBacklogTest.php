<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCopyRandomBacklogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCopy(User $user, Platform $platform, string $playStatus): GameCopy
    {
        $game = GameBase::create(['title' => 'Chrono Trigger']);
        $copy = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);
        $copy->review()->create(['user_id' => $user->id, 'game_base_id' => $game->id, 'play_status' => $playStatus]);

        return $copy;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/game-copies/random-backlog');

        $response->assertStatus(401);
    }

    public function test_returns_404_when_no_backlog_copies_exist(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $this->makeCopy($user, $platform, 'playing');

        $response = $this->actingAs($user)->getJson('/api/game-copies/random-backlog');

        $response->assertStatus(404);
    }

    public function test_never_returns_another_users_backlog_copy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $this->makeCopy($owner, $platform, 'backlog');

        $response = $this->actingAs($other)->getJson('/api/game-copies/random-backlog');

        $response->assertStatus(404);
    }

    public function test_returns_a_backlog_copy_belonging_to_the_caller(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $copy = $this->makeCopy($user, $platform, 'backlog');
        $this->makeCopy($user, $platform, 'completed');

        $response = $this->actingAs($user)->getJson('/api/game-copies/random-backlog');

        $response->assertOk();
        $response->assertJsonPath('id', $copy->id);
        $response->assertJsonPath('play_status', 'backlog');
    }
}
