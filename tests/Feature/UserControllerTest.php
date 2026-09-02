<?php

namespace Tests\Feature;

use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_expected_shape_and_counts(): void
    {
        $user = User::factory()->create();
        $viewer = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $game = GameBase::create(['title' => 'Chrono Trigger']);

        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id, 'purchase_price' => 100]);
        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id, 'purchase_price' => 50]);
        Wishlist::create(['user_id' => $user->id, 'game_base_id' => $game->id]);
        $viewer->following()->attach($user->id);

        $response = $this->actingAs($viewer)->getJson("/api/users/{$user->name}");

        $response->assertOk();
        $response->assertJsonPath('copy_count', 2);
        $response->assertJsonPath('wishlist_count', 1);
        $response->assertJsonPath('total_value', 150);
        $response->assertJsonPath('platform_count', 1);
        $response->assertJsonPath('followers_count', 1);
        $response->assertJsonPath('following_count', 0);
        $response->assertJsonPath('is_following', true);
    }

    public function test_show_for_a_guest_viewer_reports_is_following_false(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->name}");

        $response->assertOk();
        $response->assertJsonPath('is_following', false);
    }

    public function test_stats_aggregates_copies_by_platform_genre_and_decade(): void
    {
        $user = User::factory()->create();
        $snes = Platform::create(['name' => 'SNES']);
        $genesis = Platform::create(['name' => 'Genesis']);
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $chrono = GameBase::create(['title' => 'Chrono Trigger', 'release_year' => 1995]);
        $chrono->genres()->sync([$rpg->id]);
        $sonic = GameBase::create(['title' => 'Sonic', 'release_year' => 1991]);

        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'platform_id' => $snes->id, 'purchase_price' => 100]);
        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'platform_id' => $snes->id, 'purchase_price' => 50]);
        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $sonic->id, 'platform_id' => $genesis->id, 'purchase_price' => 20]);

        $response = $this->getJson("/api/users/{$user->name}/stats");

        $response->assertOk();

        $byPlatform = collect($response->json('byPlatform'))->keyBy('name');
        $this->assertSame(2, $byPlatform['SNES']['count']);
        $this->assertSame(150, $byPlatform['SNES']['value']);
        $this->assertSame(1, $byPlatform['Genesis']['count']);

        $byGenre = collect($response->json('byGenre'))->keyBy('name');
        $this->assertSame(2, $byGenre['RPG']['count']);

        // Chrono Trigger (1995) and Sonic (1991) both fall in the 1990s bucket.
        $byDecade = collect($response->json('byDecade'))->keyBy('decade');
        $this->assertSame(3, $byDecade['1990s']['count']);
    }

    public function test_stats_groups_missing_release_year_as_unknown(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $game = GameBase::create(['title' => 'Mystery Game', 'release_year' => null]);

        GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);

        $response = $this->getJson("/api/users/{$user->name}/stats");

        $response->assertOk();
        $byDecade = collect($response->json('byDecade'))->keyBy('decade');
        $this->assertSame(1, $byDecade['Unknown']['count']);
    }

    public function test_stats_aggregates_average_rating_by_genre_and_ignores_unrated_copies(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $rpg = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $chrono = GameBase::create(['title' => 'Chrono Trigger']);
        $chrono->genres()->sync([$rpg->id]);
        $ffvi = GameBase::create(['title' => 'Final Fantasy VI']);
        $ffvi->genres()->sync([$rpg->id]);

        $ratedCopyA = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'platform_id' => $platform->id]);
        $ratedCopyA->review()->create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'rating' => 5]);

        $ratedCopyB = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $ffvi->id, 'platform_id' => $platform->id]);
        $ratedCopyB->review()->create(['user_id' => $user->id, 'game_base_id' => $ffvi->id, 'rating' => 3]);

        $unratedCopy = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'platform_id' => $platform->id]);
        $unratedCopy->review()->create(['user_id' => $user->id, 'game_base_id' => $chrono->id, 'rating' => null]);

        $response = $this->getJson("/api/users/{$user->name}/stats");

        $response->assertOk();
        $byGenreRating = collect($response->json('byGenreRating'))->keyBy('name');
        $this->assertEquals(4.0, $byGenreRating['RPG']['avg_rating']);
        $this->assertSame(2, $byGenreRating['RPG']['count']);
    }

    public function test_show_reports_null_avg_rating_when_no_copies_are_rated(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $game = GameBase::create(['title' => 'Chrono Trigger']);
        $copy = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);
        $copy->review()->create(['user_id' => $user->id, 'game_base_id' => $game->id]);

        $response = $this->getJson("/api/users/{$user->name}");

        $response->assertOk();
        $response->assertJsonPath('avg_rating', null);
    }

    public function test_show_reports_the_correct_rounded_average_rating(): void
    {
        $user = User::factory()->create();
        $platform = Platform::create(['name' => 'SNES']);
        $game = GameBase::create(['title' => 'Chrono Trigger']);

        $copyA = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);
        $copyA->review()->create(['user_id' => $user->id, 'game_base_id' => $game->id, 'rating' => 5]);

        $copyB = GameCopy::create(['user_id' => $user->id, 'game_base_id' => $game->id, 'platform_id' => $platform->id]);
        $copyB->review()->create(['user_id' => $user->id, 'game_base_id' => $game->id, 'rating' => 4]);

        $response = $this->getJson("/api/users/{$user->name}");

        $response->assertOk();
        $response->assertJsonPath('avg_rating', 4.5);
    }
}
