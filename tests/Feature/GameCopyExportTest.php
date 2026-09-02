<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCopyExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeCopy(User $user, string $gameTitle, ?string $editionTitle = null): GameCopy
    {
        $game = GameBase::create(['title' => $gameTitle]);
        $platform = Platform::create(['name' => 'PlayStation 2']);

        return GameCopy::create([
            'user_id'      => $user->id,
            'game_base_id' => $game->id,
            'platform_id'  => $platform->id,
            'title'        => $editionTitle,
            'region'       => 'EU',
            'purchase_price' => 199.5,
            'purchase_date'   => '2025-01-15',
            'notes'           => 'Some notes',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/game-copies/export?format=csv&columns[]=game_title');

        $response->assertStatus(401);
    }

    public function test_export_only_includes_the_authenticated_users_own_copies(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->makeCopy($owner, 'Owner Game');
        $this->makeCopy($other, 'Other Users Game');

        $response = $this->actingAs($owner)->get('/api/game-copies/export?format=csv&columns[]=game_title');

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Owner Game', $csv);
        $this->assertStringNotContainsString('Other Users Game', $csv);
    }

    public function test_export_respects_selected_columns_and_order(): void
    {
        $user = User::factory()->create();
        $this->makeCopy($user, 'Chrono Trigger', 'Ultimate Edition');

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=title&columns[]=game_title'
        );

        $response->assertOk();
        $csv = $response->streamedContent();
        $headingLine = trim(strtok($csv, "\n"));

        $this->assertSame(['Edition / Variant', 'Game Title'], str_getcsv($headingLine));
        $this->assertStringContainsString('Ultimate Edition', $csv);
        $this->assertStringContainsString('Chrono Trigger', $csv);
        $this->assertTrue(strpos($csv, 'Ultimate Edition') < strpos($csv, 'Chrono Trigger'));
    }

    public function test_game_title_and_edition_variant_are_not_conflated(): void
    {
        $user = User::factory()->create();
        $this->makeCopy($user, 'Chrono Trigger', 'Ultimate Edition');

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=game_title&columns[]=title'
        );

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\r\n", trim($csv))));
        $row = str_getcsv($lines[1]);

        $this->assertSame(['Chrono Trigger', 'Ultimate Edition'], $row);
    }

    public function test_xlsx_format_downloads_successfully(): void
    {
        $user = User::factory()->create();
        $this->makeCopy($user, 'Final Fantasy VII');

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=xlsx&columns[]=game_title&columns[]=platform'
        );

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_parts_and_condition_column_is_flattened_into_one_cell(): void
    {
        $user = User::factory()->create();
        $copy = $this->makeCopy($user, 'Metal Gear Solid');
        $condition = Condition::create(['name' => 'Mint']);
        $copy->parts()->create(['type' => 'Disc', 'condition_id' => $condition->id]);

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=parts'
        );

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Disc: Mint', $csv);
    }

    public function test_play_status_rating_and_hours_played_columns_are_exportable(): void
    {
        $user = User::factory()->create();
        $copy = $this->makeCopy($user, 'Chrono Trigger');
        $copy->review()->create([
            'user_id' => $user->id,
            'game_base_id' => $copy->game_base_id,
            'play_status' => 'completed',
            'rating' => 5,
            'hours_played' => 42.5,
        ]);

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=play_status&columns[]=rating&columns[]=hours_played'
        );

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Completed', $csv);
        $this->assertStringContainsString('5', $csv);
        $this->assertStringContainsString('42.5', $csv);
    }

    public function test_playthrough_count_and_would_replay_recommend_columns_are_exportable(): void
    {
        $user = User::factory()->create();
        $copy = $this->makeCopy($user, 'Chrono Trigger');
        $copy->review()->create([
            'user_id' => $user->id,
            'game_base_id' => $copy->game_base_id,
            'playthrough_count' => 3,
            'would_replay' => true,
            'would_recommend' => false,
        ]);

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=playthrough_count&columns[]=would_replay&columns[]=would_recommend'
        );

        $response->assertOk();
        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $row = str_getcsv($lines[1]);

        $this->assertSame(['3', 'Yes', 'No'], $row);
    }

    public function test_unset_would_replay_recommend_export_as_empty_string(): void
    {
        $user = User::factory()->create();
        $this->makeCopy($user, 'Chrono Trigger');

        $response = $this->actingAs($user)->get(
            '/api/game-copies/export?format=csv&columns[]=game_title&columns[]=would_replay&columns[]=would_recommend'
        );

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Chrono Trigger', $csv);
        $this->assertStringNotContainsString('Yes', $csv);
        $this->assertStringNotContainsString('No', $csv);
    }

    public function test_invalid_column_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            '/api/game-copies/export?format=csv&columns[]=not_a_real_column'
        );

        $response->assertStatus(422);
    }

    public function test_invalid_format_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(
            '/api/game-copies/export?format=pdf&columns[]=game_title'
        );

        $response->assertStatus(422);
    }
}
