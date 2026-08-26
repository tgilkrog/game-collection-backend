<?php

namespace Tests\Feature;

use App\Services\IgdbService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IgdbServiceTest extends TestCase
{
    public function test_failed_token_fetch_is_not_cached(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $result = app(IgdbService::class)->search('Chrono Trigger');

        $this->assertSame([], $result);
        $this->assertFalse(Cache::has('igdb_access_token'));
    }

    public function test_successful_token_fetch_is_cached_and_reused(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 5000000], 200),
            'api.igdb.com/*' => Http::response([], 200),
        ]);

        app(IgdbService::class)->search('Chrono Trigger');

        $this->assertTrue(Cache::has('igdb_access_token'));
        $this->assertSame('fake-token', Cache::get('igdb_access_token'));

        Http::assertSentCount(2); // one Twitch token call, one IGDB games call
    }

    public function test_search_query_strips_semicolons_to_prevent_query_injection(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 5000000], 200),
            'api.igdb.com/*' => Http::response([], 200),
        ]);

        app(IgdbService::class)->search('Chrono Trigger"; fields *; limit 500;');

        // The user-supplied query lands inside the search "..." clause — assert no
        // semicolon survives there, so it can't terminate the statement early and
        // append attacker-controlled clauses (e.g. a widened `limit`).
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'igdb.com')
            || ! preg_match('/search "[^"]*;[^"]*"/', $request->body()));
    }
}
