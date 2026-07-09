<?php

namespace Tests\Feature;

use App\Models\GameMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameModeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_and_ordered_by_name(): void
    {
        GameMode::create(['name' => 'Multiplayer', 'slug' => 'multiplayer']);
        GameMode::create(['name' => 'Co-operative', 'slug' => 'co-operative']);

        $response = $this->getJson('/api/game-modes');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame(['Co-operative', 'Multiplayer'], $names);
    }

    public function test_index_returns_expected_shape(): void
    {
        GameMode::create(['name' => 'Single player', 'slug' => 'single-player', 'igdb_id' => 1]);

        $response = $this->getJson('/api/game-modes');

        $response->assertOk();
        $response->assertJsonFragment([
            'name'    => 'Single player',
            'slug'    => 'single-player',
            'igdb_id' => 1,
        ]);
    }
}
