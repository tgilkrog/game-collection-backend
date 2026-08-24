<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_param_returns_all_conditions(): void
    {
        Condition::create(['name' => 'Mint']);
        Condition::create(['name' => 'Poor']);

        $response = $this->getJson('/api/conditions');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_in_use_only_returns_conditions_assigned_to_a_copy(): void
    {
        $used = Condition::create(['name' => 'Mint']);
        Condition::create(['name' => 'Poor']);

        $user = User::factory()->create();
        $game = GameBase::create(['title' => 'Game A']);
        $platform = Platform::create(['name' => 'SNES']);
        $copy = GameCopy::create([
            'user_id' => $user->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);
        $copy->parts()->create(['type' => 'Cartridge', 'condition_id' => $used->id]);

        $response = $this->getJson('/api/conditions?in_use=1');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame($used->id, $response->json('0.id'));
    }
}
