<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBaseFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_filters_returns_everything(): void
    {
        GameBase::create(['title' => 'Game A']);
        GameBase::create(['title' => 'Game B']);

        $response = $this->getJson('/api/game-base');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_genre_filter_narrows_to_tagged_games(): void
    {
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        Genre::create(['name' => 'Action', 'slug' => 'action']);

        $tagged = GameBase::create(['title' => 'Tagged Game']);
        $tagged->genres()->sync([$rpg->id]);
        GameBase::create(['title' => 'Untagged Game']);

        $response = $this->getJson("/api/game-base?genre_id[]={$rpg->id}");

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['Tagged Game'], $titles);
    }

    public function test_multiple_values_in_one_facet_are_ored(): void
    {
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        $action = Genre::create(['name' => 'Action', 'slug' => 'action']);

        $rpgGame = GameBase::create(['title' => 'RPG Game']);
        $rpgGame->genres()->sync([$rpg->id]);

        $actionGame = GameBase::create(['title' => 'Action Game']);
        $actionGame->genres()->sync([$action->id]);

        GameBase::create(['title' => 'Neither Game']);

        $response = $this->getJson("/api/game-base?genre_id[]={$rpg->id}&genre_id[]={$action->id}");

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();
        $this->assertSame(['Action Game', 'RPG Game'], $titles);
    }

    public function test_different_facets_are_anded(): void
    {
        $user = User::factory()->create();
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        $platform = Platform::create(['name' => 'SNES']);
        $otherPlatform = Platform::create(['name' => 'Genesis']);

        $matchesBoth = GameBase::create(['title' => 'Matches Both']);
        $matchesBoth->genres()->sync([$rpg->id]);
        GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $matchesBoth->id,
            'platform_id' => $platform->id,
        ]);

        $matchesGenreOnly = GameBase::create(['title' => 'Matches Genre Only']);
        $matchesGenreOnly->genres()->sync([$rpg->id]);
        GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $matchesGenreOnly->id,
            'platform_id' => $otherPlatform->id,
        ]);

        $matchesPlatformOnly = GameBase::create(['title' => 'Matches Platform Only']);
        GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $matchesPlatformOnly->id,
            'platform_id' => $platform->id,
        ]);

        $response = $this->getJson("/api/game-base?genre_id[]={$rpg->id}&platform_id[]={$platform->id}");

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['Matches Both'], $titles);
    }

}
