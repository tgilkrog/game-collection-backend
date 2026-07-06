<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BarcodeLookupService
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.upcitemdb.key');
    }

    public function lookup(string $barcode): ?array
    {
        $response = $this->apiKey
            ? Http::withHeaders([
                'user_key' => $this->apiKey,
                'key_type' => '3scale',
              ])->get('https://api.upcitemdb.com/prod/v1/lookup', ['upc' => $barcode])
            : Http::get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $barcode]);

        if (!$response->ok()) {
            return null;
        }

        $items = $response->json('items') ?? [];

        if (empty($items)) {
            return null;
        }

        $item = $items[0];

        return [
            'barcode' => $barcode,
            'title'   => isset($item['title']) ? $this->cleanTitle($item['title']) : null,
            'brand'   => $item['brand'] ?? null,
            'image'   => $item['images'][0] ?? null,
        ];
    }

    /**
     * Retail UPC listings commonly tack the platform onto the title, e.g.
     * "ps2 Biker Mice From Mars - Sony PlayStation 2" or "Infamous - PlayStation 3".
     * IGDB titles don't include that, so strip it to make the search match better.
     */
    private function cleanTitle(string $title): string
    {
        $platform = '(?:sony\s+)?playstation\s*[1-5]?|ps\s?[1-5]|psp|ps\s?vita'
            . '|xbox(?:\s*(?:360|one|series\s*[xs]))?|nintendo\s*(?:switch|64|gamecube)?'
            . '|wii\s*u?|gamecube|game\s*boy(?:\s*(?:advance|color))?|3ds|nds|ds\s*lite'
            . '|sega\s*(?:genesis|saturn|dreamcast|mega\s*drive)?|for\s+pc';

        $title = preg_replace('/^\s*(?:ps[1-5]|psp|xbox(?:360|one)?|wiiu?|gba|nds|3ds)\s+/i', '', $title);

        if (preg_match('/^(.*?)\s*[-–—:]\s*([^-–—:]+)$/', $title, $matches)) {
            $tail = trim(preg_replace('/\b(?:' . $platform . ')\b/i', '', $matches[2]));
            if ($tail === '') {
                $title = $matches[1];
            }
        }

        return trim($title);
    }
}
