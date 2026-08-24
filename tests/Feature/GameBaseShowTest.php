<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBaseShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_no_longer_returns_game_copies(): void
    {
        $game = GameBase::create(['title' => 'Game A']);

        $response = $this->getJson("/api/game-base/{$game->id}");

        $response->assertOk();
        $this->assertArrayNotHasKey('game_copies', $response->json());
    }

    public function test_copies_from_other_users_are_visible_via_game_copies_endpoint(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $platform = Platform::create(['name' => 'SNES']);

        $copy = GameCopy::create([
            'user_id' => $owner->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);

        $response = $this->actingAs($viewer)->getJson("/api/game-copies?game_base_id[]={$game->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($copy->id, $response->json('data.0.id'));
        $this->assertSame($owner->id, $response->json('data.0.user.id'));
    }
}
