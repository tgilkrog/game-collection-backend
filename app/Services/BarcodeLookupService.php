<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BarcodeLookupService
{
    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.upcitemdb.key');
    }

    public function lookup(string $barcode): ?array
    {
        try {
            $response = $this->apiKey
                ? Http::withHeaders([
                    'user_key' => $this->apiKey,
                    'key_type' => '3scale',
                ])->get('https://api.upcitemdb.com/prod/v1/lookup', ['upc' => $barcode])
                : Http::get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $barcode]);
        } catch (\Throwable $e) {
            Log::warning('Barcode lookup failed', [
                'barcode' => $barcode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $items = $response->json('items') ?? [];

        if (empty($items)) {
            return null;
        }

        $item = $items[0];

        // The top-level item title is aggregated from the UPC/EAN registry and is often
        // packed with seller noise (condition, "brand & sealed", region, etc.). The first
        // offer's title is sourced from an actual retail listing and is usually much
        // closer to the real product name, so prefer it when a listing is present.
        $offerTitle = $item['offers'][0]['title'] ?? null;
        $rawTitle = $offerTitle ?: ($item['title'] ?? null);

        return [
            'barcode' => $barcode,
            'title' => $rawTitle !== null ? $this->cleanTitle($rawTitle) : null,
            'brand' => $item['brand'] ?? null,
            'image' => $item['images'][0] ?? null,
        ];
    }

    /**
     * Retail UPC listings commonly tack the platform onto the title in several shapes,
     * e.g. "ps2 Biker Mice From Mars", "Infamous - PlayStation 3", "Blade II (PS2)",
     * "Blood On The Sand PS3". IGDB titles don't include any of that, so strip it to
     * make the search match better.
     */
    private function cleanTitle(string $title): string
    {
        $platform = '(?:sony\s+)?playstation\s*[1-5]?|ps\s?[1-5]|psp|ps\s?vita'
            .'|xbox(?:\s*(?:360|one|series\s*[xs]))?|nintendo\s*(?:switch|64|gamecube)?'
            .'|wii\s*u?|gamecube|game\s*boy(?:\s*(?:advance|color))?|3ds|nds|ds\s*lite'
            .'|sega\s*(?:genesis|saturn|dreamcast|mega\s*drive)?|for\s+pc';

        // Leading platform abbreviation, e.g. "ps2 Biker Mice..." -> "Biker Mice...".
        $title = preg_replace('/^\s*(?:ps[1-5]|psp|xbox(?:360|one)?|wiiu?|gba|nds|3ds)\s+/i', '', $title);

        // Trailing "(PS2)" / "[PlayStation 3]" / "(2006)" style metadata blocks — listings
        // always tack these on after the real title, never as part of it.
        while (preg_match('/\s*[\(\[][^\)\]]*[\)\]]\s*$/', $title)) {
            $title = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*$/', '', $title);
        }

        // Trailing " - <platform words only>" / ": <platform words only>", e.g.
        // "Infamous - Playstation 3" -> "Infamous". Only strips if the whole tail reduces
        // to nothing, so real subtitles like "Mass Effect - Legendary Edition" survive.
        if (preg_match('/^(.*?)\s*[-–—:]\s*([^-–—:]+)$/', $title, $matches)) {
            $tail = trim(preg_replace('/\b(?:'.$platform.')\b/i', '', $matches[2]));
            if ($tail === '') {
                $title = $matches[1];
            }
        }

        // Trailing bare platform word with no separator, e.g. "Blood On The Sand PS3".
        $title = preg_replace('/\s+(?:'.$platform.')\s*$/i', '', $title);

        return trim($title);
    }
}
