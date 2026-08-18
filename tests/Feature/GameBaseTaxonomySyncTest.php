<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\PlayerPerspective;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameBaseTaxonomySyncTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_store_syncs_all_four_taxonomies(): void
    {
        $genre = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        $theme = Theme::create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        $mode = GameMode::create(['name' => 'Single Player', 'slug' => 'single-player']);
        $perspective = PlayerPerspective::create(['name' => 'Third Person', 'slug' => 'third-person']);

        $response = $this->actingAs($this->admin())->postJson('/api/game-base', [
            'title' => 'Custom Game',
            'release_year' => 2001,
            'genres' => [$genre->id],
            'themes' => [$theme->id],
            'game_modes' => [$mode->id],
            'player_perspectives' => [$perspective->id],
        ]);

        $response->assertOk();

        $game = GameBase::where('title', 'Custom Game')->firstOrFail();
        $this->assertSame([$genre->id], $game->genres()->pluck('genres.id')->all());
        $this->assertSame([$theme->id], $game->themes()->pluck('themes.id')->all());
        $this->assertSame([$mode->id], $game->gameModes()->pluck('game_modes.id')->all());
        $this->assertSame([$perspective->id], $game->playerPerspectives()->pluck('player_perspectives.id')->all());
    }

    public function test_update_replaces_taxonomy_sync(): void
    {
        $oldTheme = Theme::create(['name' => 'Horror', 'slug' => 'horror']);
        $newTheme = Theme::create(['name' => 'Sci-Fi', 'slug' => 'sci-fi']);

        $game = GameBase::create(['title' => 'Custom Game', 'release_year' => 2001]);
        $game->themes()->sync([$oldTheme->id]);

        $response = $this->actingAs($this->admin())->putJson("/api/game-base/{$game->id}", [
            'title' => 'Custom Game',
            'release_year' => 2001,
            'themes' => [$newTheme->id],
        ]);

        $response->assertOk();
        $this->assertSame([$newTheme->id], $game->themes()->pluck('themes.id')->all());
    }

    public function test_show_endpoint_includes_all_four_synced_taxonomies(): void
    {
        $genre = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);
        $theme = Theme::create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        $mode = GameMode::create(['name' => 'Single Player', 'slug' => 'single-player']);
        $perspective = PlayerPerspective::create(['name' => 'Third Person', 'slug' => 'third-person']);

        $game = GameBase::create(['title' => 'Custom Game', 'release_year' => 2001]);
        $game->genres()->sync([$genre->id]);
        $game->themes()->sync([$theme->id]);
        $game->gameModes()->sync([$mode->id]);
        $game->playerPerspectives()->sync([$perspective->id]);

        $response = $this->getJson("/api/game-base/{$game->id}");

        $response->assertOk();
        $response->assertJsonPath('genres.0.id', $genre->id);
        $response->assertJsonPath('themes.0.id', $theme->id);
        $response->assertJsonPath('game_modes.0.id', $mode->id);
        $response->assertJsonPath('player_perspectives.0.id', $perspective->id);
    }
}
