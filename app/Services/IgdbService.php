<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IgdbService
{
    private string $clientId;
    private string $clientSecret;
    private string $base = 'https://api.igdb.com/v4';

    public function __construct()
    {
        $this->clientId     = env('IGDB_CLIENT_ID');
        $this->clientSecret = env('IGDB_CLIENT_SECRET');
    }

    private function token(): string
    {
        return Cache::remember('igdb_access_token', now()->addDays(55), function () {
            $response = Http::post('https://id.twitch.tv/oauth2/token', [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'client_credentials',
            ]);

            return $response->json('access_token');
        });
    }

    private function query(string $endpoint, string $body): array
    {
        $response = Http::withHeaders([
            'Client-ID'     => $this->clientId,
            'Authorization' => 'Bearer ' . $this->token(),
        ])->withBody($body, 'text/plain')
          ->post("{$this->base}/{$endpoint}");

        return $response->ok() ? ($response->json() ?? []) : [];
    }

    private function coverUrl(?string $imageId): ?string
    {
        if (!$imageId) return null;
        return "https://images.igdb.com/igdb/image/upload/t_cover_big/{$imageId}.jpg";
    }

    public function search(string $query): array
    {
        $games = $this->query(
            'games',
            "search \"{$query}\"; fields id,name,cover.image_id,first_release_date,platforms.abbreviation; limit 10;"
        );

        return collect($games)->map(fn($game) => [
            'source'       => 'igdb',
            'igdb_id'      => $game['id'],
            'title'        => $game['name'],
            'cover_image'  => $this->coverUrl($game['cover']['image_id'] ?? null),
            'release_year' => isset($game['first_release_date'])
                                ? (int) date('Y', $game['first_release_date'])
                                : null,
            'platforms'    => collect($game['platforms'] ?? [])->pluck('abbreviation')->filter()->values()->all(),
        ])->all();
    }

    public function find(int $igdbId): array
    {
        $games = $this->query(
            'games',
            "where id = {$igdbId}; fields id,name,first_release_date,cover.image_id,involved_companies.company.name,involved_companies.developer,involved_companies.publisher,summary,genres.id,genres.name,themes.id,themes.name,game_modes.id,game_modes.name,player_perspectives.id,player_perspectives.name; limit 1;"
        );

        if (empty($games)) return [];

        $game = $games[0];

        $companies = collect($game['involved_companies'] ?? []);
        $developer = $companies->firstWhere('developer', true)['company']['name'] ?? null;
        $publisher = $companies->firstWhere('publisher', true)['company']['name'] ?? null;

        return [
            'igdb_id'      => $game['id'],
            'title'        => $game['name'],
            'release_year' => isset($game['first_release_date'])
                                ? (int) date('Y', $game['first_release_date'])
                                : null,
            'developer'    => $developer,
            'publisher'    => $publisher,
            'description'  => isset($game['summary']) ? strip_tags($game['summary']) : null,
            'cover_image'  => $this->coverUrl($game['cover']['image_id'] ?? null),
            'genres'              => collect($game['genres'] ?? [])->map(fn($g) => ['id' => $g['id'], 'name' => $g['name']])->all(),
            'themes'              => collect($game['themes'] ?? [])->map(fn($t) => ['id' => $t['id'], 'name' => $t['name']])->all(),
            'game_modes'          => collect($game['game_modes'] ?? [])->map(fn($m) => ['id' => $m['id'], 'name' => $m['name']])->all(),
            'player_perspectives' => collect($game['player_perspectives'] ?? [])->map(fn($p) => ['id' => $p['id'], 'name' => $p['name']])->all(),
        ];
    }
}
