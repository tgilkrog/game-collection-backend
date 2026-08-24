<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCopyFilterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCopy(User $user, GameBase $game, Platform $platform): GameCopy
    {
        return GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);
    }

    public function test_no_filters_returns_everything(): void
    {
        $user = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $platform = Platform::create(['name' => 'SNES']);
        $this->makeCopy($user, $game, $platform);
        $this->makeCopy($user, $game, $platform);

        $response = $this->getJson('/api/game-copies');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_platform_filter_supports_multiple_values(): void
    {
        $user = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $snes = Platform::create(['name' => 'SNES']);
        $genesis = Platform::create(['name' => 'Genesis']);
        $ps2 = Platform::create(['name' => 'PS2']);

        $this->makeCopy($user, $game, $snes);
        $this->makeCopy($user, $game, $genesis);
        $this->makeCopy($user, $game, $ps2);

        $response = $this->getJson("/api/game-copies?platform_id[]={$snes->id}&platform_id[]={$genesis->id}");

        $response->assertOk();
        $platformNames = collect($response->json('data'))->pluck('platform.name')->sort()->values()->all();
        $this->assertSame(['Genesis', 'SNES'], $platformNames);
    }

    public function test_condition_filter(): void
    {
        $user = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $platform = Platform::create(['name' => 'SNES']);
        $mint = Condition::create(['name' => 'Mint']);
        $poor = Condition::create(['name' => 'Poor']);

        $mintCopy = $this->makeCopy($user, $game, $platform);
        $mintCopy->parts()->create(['type' => 'Cartridge', 'condition_id' => $mint->id]);

        $poorCopy = $this->makeCopy($user, $game, $platform);
        $poorCopy->parts()->create(['type' => 'Cartridge', 'condition_id' => $poor->id]);

        $response = $this->getJson("/api/game-copies?condition_id[]={$mint->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($mintCopy->id, $response->json('data.0.id'));
    }

    public function test_genre_filter_via_game_relation(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $rpgGame = GameBase::create(['title' => 'RPG Game']);
        $rpgGame->genres()->sync([$rpg->id]);
        $rpgCopy = $this->makeCopy($user, $rpgGame, $platform);

        $otherGame = GameBase::create(['title' => 'Other Game']);
        $this->makeCopy($user, $otherGame, $platform);

        $response = $this->getJson("/api/game-copies?genre_id[]={$rpg->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($rpgCopy->id, $response->json('data.0.id'));
    }

    public function test_game_base_id_filter(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $gameA = GameBase::create(['title' => 'Game A']);
        $gameB = GameBase::create(['title' => 'Game B']);

        $copyA = $this->makeCopy($user, $gameA, $platform);
        $this->makeCopy($user, $gameB, $platform);

        $response = $this->getJson("/api/game-copies?game_base_id[]={$gameA->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($copyA->id, $response->json('data.0.id'));
    }

    public function test_exclude_ids_filter(): void
    {
        $user = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $platform = Platform::create(['name' => 'SNES']);

        $keep = $this->makeCopy($user, $game, $platform);
        $excluded = $this->makeCopy($user, $game, $platform);

        $response = $this->getJson("/api/game-copies?exclude_ids[]={$excluded->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($keep->id, $response->json('data.0.id'));
    }

    public function test_facets_combine_with_and(): void
    {
        $user = User::factory()->create();
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        $snes = Platform::create(['name' => 'SNES']);
        $genesis = Platform::create(['name' => 'Genesis']);

        $rpgGame = GameBase::create(['title' => 'RPG Game']);
        $rpgGame->genres()->sync([$rpg->id]);

        $matchesBoth = $this->makeCopy($user, $rpgGame, $snes);
        $this->makeCopy($user, $rpgGame, $genesis); // matches genre only

        $otherGame = GameBase::create(['title' => 'Other Game']);
        $this->makeCopy($user, $otherGame, $snes); // matches platform only

        $response = $this->getJson("/api/game-copies?genre_id[]={$rpg->id}&platform_id[]={$snes->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($matchesBoth->id, $response->json('data.0.id'));
    }
}
