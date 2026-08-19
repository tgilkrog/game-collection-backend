<?php

namespace Tests\Feature;

use App\Models\GameMode;
use App\Models\PlayerPerspective;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaxonomyAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public static function endpoints(): array
    {
        return [
            'themes' => ['themes', Theme::class, 'themes'],
            'game-modes' => ['game-modes', GameMode::class, 'game_modes'],
            'player-perspectives' => ['player-perspectives', PlayerPerspective::class, 'player_perspectives'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_guest_cannot_write(string $uri, string $model, string $table): void
    {
        $response = $this->postJson("/api/{$uri}", ['name' => 'Foo', 'slug' => 'foo']);

        $response->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_non_admin_cannot_write(string $uri, string $model, string $table): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/{$uri}", ['name' => 'Foo', 'slug' => 'foo']);

        $response->assertForbidden();
        $this->assertDatabaseMissing($table, ['slug' => 'foo']);
    }

    #[DataProvider('endpoints')]
    public function test_admin_can_create(string $uri, string $model, string $table): void
    {
        $response = $this->actingAs($this->admin())->postJson("/api/{$uri}", ['name' => 'Foo', 'slug' => 'foo']);

        $response->assertSuccessful();
        $this->assertDatabaseHas($table, ['name' => 'Foo', 'slug' => 'foo']);
    }

    #[DataProvider('endpoints')]
    public function test_admin_can_update(string $uri, string $model, string $table): void
    {
        $term = $model::create(['name' => 'Foo', 'slug' => 'foo']);

        $response = $this->actingAs($this->admin())->putJson("/api/{$uri}/{$term->id}", ['name' => 'Bar']);

        $response->assertSuccessful();
        $this->assertDatabaseHas($table, ['id' => $term->id, 'name' => 'Bar']);
    }

    #[DataProvider('endpoints')]
    public function test_admin_can_delete(string $uri, string $model, string $table): void
    {
        $term = $model::create(['name' => 'Foo', 'slug' => 'foo']);

        $response = $this->actingAs($this->admin())->deleteJson("/api/{$uri}/{$term->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing($table, ['id' => $term->id]);
    }
}
