<?php

namespace Tests\Feature;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_index_no_longer_requires_authentication(): void
    {
        Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $response = $this->getJson('/api/genres');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'RPG']);
    }

    public function test_store_still_requires_authentication(): void
    {
        $response = $this->postJson('/api/genres', ['name' => 'Action', 'slug' => 'action']);

        $response->assertStatus(401);
    }

    public function test_update_still_requires_authentication(): void
    {
        $genre = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $response = $this->putJson("/api/genres/{$genre->id}", ['name' => 'JRPG']);

        $response->assertStatus(401);
    }

    public function test_destroy_still_requires_authentication(): void
    {
        $genre = Genre::create(['name' => 'RPG', 'slug' => 'rpg']);

        $response = $this->deleteJson("/api/genres/{$genre->id}");

        $response->assertStatus(401);
    }

    public function test_non_admin_authenticated_user_cannot_write(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/genres', [
            'name' => 'Action',
            'slug' => 'action',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('genres', ['name' => 'Action', 'slug' => 'action']);
    }

    public function test_admin_can_write(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/genres', [
            'name' => 'Action',
            'slug' => 'action',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('genres', ['name' => 'Action', 'slug' => 'action']);
    }
}
