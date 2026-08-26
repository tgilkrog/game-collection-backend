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
                    'title' => 'Chrono Trigger',
                    'brand' => 'Square',
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

    public function test_trailing_platform_suffix_is_stripped_from_title(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Infamous - Playstation 3',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Infamous');
    }

    public function test_leading_and_trailing_platform_noise_is_stripped_from_title(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'ps2 Biker mice from mars - sony playstation 2',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Biker mice from mars');
    }

    public function test_legitimate_subtitle_with_a_hyphen_is_not_stripped(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Mass Effect - Legendary Edition',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Mass Effect - Legendary Edition');
    }

    public function test_offer_title_is_preferred_over_noisy_item_title(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Ps2 Biker Mice From Mars (2006), Uk Pal, Brand & Sony Factory Sealed',
                    'offers' => [['title' => 'Biker Mice From Mars (PS2)']],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Biker Mice From Mars');
    }

    public function test_bracketed_platform_suffix_is_stripped_from_offer_title(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Thq 50 Cent: Blood On The Sand Ps3 [playstation 3]',
                    'offers' => [['title' => 'Blade II (PS2)']],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Blade II');
    }

    public function test_bare_trailing_platform_word_is_stripped(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'offers' => [['title' => '50 CENT: BLOOD ON THE SAND PS3']],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', '50 CENT: BLOOD ON THE SAND');
    }

    public function test_falls_back_to_item_title_when_no_offers_present(): void
    {
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Infamous - Playstation 3',
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Infamous');
    }

    public function test_non_platform_edition_label_is_not_stripped(): void
    {
        // "Platinum" is Sony's PS1 budget-reissue label, not a platform name — the cleaner
        // deliberately doesn't guess away words it doesn't recognize as platform noise, to
        // avoid accidentally eating real title content. Documents a known limitation.
        Http::fake([
            'api.upcitemdb.com/*' => Http::response([
                'items' => [[
                    'title' => 'Medievil - Sony Playstation Ps1 Platinum Brand And Sealed',
                    'offers' => [['title' => 'Medievil - Platinum']],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

        $response->assertOk();
        $response->assertJsonPath('result.title', 'Medievil - Platinum');
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

    public function test_network_failure_degrades_gracefully_instead_of_500ing(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/barcode-lookup?barcode=012345678905');

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
