<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameCopyIgdbImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unresolvable_igdb_id_returns_422_instead_of_crashing(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 5000000], 200),
            'api.igdb.com/*' => Http::response([], 200), // IGDB found nothing for this id
        ]);

        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'PlayStation 2']);

        $response = $this->actingAs($user)->postJson('/api/game-copies', [
            'igdb_id' => 999999999,
            'platform_id' => $platform->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, GameCopy::count());
        $this->assertSame(0, GameBase::count());
    }

    public function test_valid_igdb_id_still_creates_a_game_copy(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 5000000], 200),
            'api.igdb.com/*' => Http::response([[
                'id' => 12345,
                'name' => 'Chrono Trigger',
            ]], 200),
        ]);

        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);

        $response = $this->actingAs($user)->postJson('/api/game-copies', [
            'igdb_id' => 12345,
            'platform_id' => $platform->id,
        ]);

        $response->assertCreated();
        $this->assertSame(1, GameCopy::count());
        $this->assertSame('Chrono Trigger', GameBase::firstOrFail()->title);
    }
}
