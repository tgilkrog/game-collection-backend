<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_guest_cannot_write(): void
    {
        $response = $this->postJson('/api/conditions', ['name' => 'Mint']);

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_write(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/conditions', ['name' => 'Mint']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('conditions', ['name' => 'Mint']);
    }

    public function test_admin_can_create_and_update(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/conditions', ['name' => 'Mint']);
        $response->assertSuccessful();
        $condition = Condition::firstWhere('name', 'Mint');
        $this->assertNotNull($condition);

        $response = $this->actingAs($admin)->putJson("/api/conditions/{$condition->id}", ['name' => 'Near Mint']);
        $response->assertSuccessful();
        $this->assertDatabaseHas('conditions', ['id' => $condition->id, 'name' => 'Near Mint']);
    }

    public function test_admin_can_delete_condition_not_in_use(): void
    {
        $condition = Condition::create(['name' => 'Mint']);

        $response = $this->actingAs($this->admin())->deleteJson("/api/conditions/{$condition->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('conditions', ['id' => $condition->id]);
    }

    public function test_admin_cannot_delete_condition_in_use(): void
    {
        $condition = Condition::create(['name' => 'Mint']);
        $platform = Platform::create(['name' => 'PlayStation 2']);
        $game = GameBase::create(['title' => 'Shadow of the Colossus']);
        $owner = User::factory()->create();
        $copy = GameCopy::create([
            'user_id' => $owner->id,
            'game_base_id' => $game->id,
            'platform_id' => $platform->id,
        ]);
        $copy->parts()->create(['type' => 'Disc', 'condition_id' => $condition->id]);

        $response = $this->actingAs($this->admin())->deleteJson("/api/conditions/{$condition->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('conditions', ['id' => $condition->id]);
    }
}
