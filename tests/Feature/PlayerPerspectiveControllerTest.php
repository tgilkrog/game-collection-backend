<?php

namespace Tests\Feature;

use App\Models\PlayerPerspective;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPerspectiveControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_and_ordered_by_name(): void
    {
        PlayerPerspective::create(['name' => 'Third person', 'slug' => 'third-person']);
        PlayerPerspective::create(['name' => 'First person', 'slug' => 'first-person']);

        $response = $this->getJson('/api/player-perspectives');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame(['First person', 'Third person'], $names);
    }

    public function test_index_returns_expected_shape(): void
    {
        PlayerPerspective::create(['name' => 'Bird view', 'slug' => 'bird-view', 'igdb_id' => 7]);

        $response = $this->getJson('/api/player-perspectives');

        $response->assertOk();
        $response->assertJsonFragment([
            'name'    => 'Bird view',
            'slug'    => 'bird-view',
            'igdb_id' => 7,
        ]);
    }
}
