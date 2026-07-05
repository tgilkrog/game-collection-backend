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
            'title'   => $item['title'] ?? null,
            'brand'   => $item['brand'] ?? null,
            'image'   => $item['images'][0] ?? null,
        ];
    }
}
