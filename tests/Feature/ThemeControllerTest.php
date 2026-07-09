<?php

namespace Tests\Feature;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_public_and_ordered_by_name(): void
    {
        Theme::create(['name' => 'Survival', 'slug' => 'survival']);
        Theme::create(['name' => 'Horror', 'slug' => 'horror']);

        $response = $this->getJson('/api/themes');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame(['Horror', 'Survival'], $names);
    }

    public function test_index_returns_expected_shape(): void
    {
        Theme::create(['name' => 'Horror', 'slug' => 'horror', 'igdb_id' => 42]);

        $response = $this->getJson('/api/themes');

        $response->assertOk();
        $response->assertJsonFragment([
            'name'    => 'Horror',
            'slug'    => 'horror',
            'igdb_id' => 42,
        ]);
    }
}
