<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BarcodeLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertStatus(401);
    }

    public function test_found_barcode_returns_matched_result(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title'  => 'Chrono Trigger',
                    'brand'  => 'Square',
                    'images' => ['http://example.com/img.jpg'],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJson(['matched' => true]);
        $response->assertJsonPath('result.title', 'Chrono Trigger');
        $response->assertJsonPath('result.barcode', '012345678905');
    }

    public function test_not_found_barcode_returns_matched_false_not_an_error(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response(['items' => []], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=000000000000');

        $response->assertOk();
        $response->assertJson(['matched' => false, 'result' => null]);
    }

    public function test_missing_barcode_param_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup');

        $response->assertStatus(422);
    }

    public function test_requests_beyond_the_rate_limit_are_throttled(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response(['items' => []], 200),
        ]);

        $user = User::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905')->assertOk();
        }

        $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905')->assertStatus(429);
    }
}
