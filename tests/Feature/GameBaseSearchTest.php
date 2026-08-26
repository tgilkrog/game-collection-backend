<?php

namespace Tests\Feature;

use App\Models\GameBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameBaseSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_source_returns_only_local_matches(): void
    {
        GameBase::create(['title' => 'Chrono Trigger']);
        GameBase::create(['title' => 'Chrono Cross']);
        GameBase::create(['title' => 'Final Fantasy VII']);

        $response = $this->getJson('/api/game-base/search?q=Chrono&source=local');

        $response->assertOk();
        $titles = collect($response->json())->pluck('title')->sort()->values()->all();
        $this->assertSame(['Chrono Cross', 'Chrono Trigger'], $titles);
    }

    public function test_default_search_merges_local_and_igdb_results_deduplicating_by_title(): void
    {
        GameBase::create(['title' => 'Chrono Trigger', 'igdb_id' => 1]);

        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 5000000], 200),
            'api.igdb.com/*' => Http::response([
                ['id' => 1, 'name' => 'Chrono Trigger'],
                ['id' => 2, 'name' => 'Chrono Cross'],
            ], 200),
        ]);

        $response = $this->getJson('/api/game-base/search?q=Chrono');

        $response->assertOk();
        $titles = collect($response->json())->pluck('title')->all();

        $this->assertSame(['Chrono Trigger', 'Chrono Cross'], $titles);
    }

    public function test_overlong_query_is_rejected(): void
    {
        $response = $this->getJson('/api/game-base/search?q='.str_repeat('a', 101));

        $response->assertStatus(422);
    }

    public function test_invalid_source_is_rejected(): void
    {
        $response = $this->getJson('/api/game-base/search?q=chrono&source=bogus');

        $response->assertStatus(422);
    }
}
