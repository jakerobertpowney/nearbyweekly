<?php

namespace App\Services\Geocoding;

use App\Contracts\PostcodeGeocoder;
use App\Models\Postcode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PostcodesIoGeocoder implements PostcodeGeocoder
{
    /**
     * Resolve a postcode, checking the local cache before hitting the API.
     *
     * @return array{postcode: string, latitude: float, longitude: float}|null
     */
    public function geocode(string $postcode): ?array
    {
        $normalized = Str::upper(trim(preg_replace('/\s+/', ' ', $postcode) ?? ''));

        $cached = Postcode::query()->find($normalized);

        if ($cached !== null) {
            return [
                'postcode' => $cached->postcode,
                'latitude' => $cached->latitude,
                'longitude' => $cached->longitude,
            ];
        }

        $response = Http::baseUrl(config('services.postcodes_io.base_url'))
            ->acceptJson()
            ->get('/postcodes/'.rawurlencode($normalized));

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json('result');

        if (! is_array($result) || ! isset($result['latitude'], $result['longitude'])) {
            return null;
        }

        $geo = [
            'postcode' => $result['postcode'] ?? $normalized,
            'latitude' => (float) $result['latitude'],
            'longitude' => (float) $result['longitude'],
        ];

        Postcode::query()->firstOrCreate(
            ['postcode' => $geo['postcode']],
            ['latitude' => $geo['latitude'], 'longitude' => $geo['longitude'], 'created_at' => now()],
        );

        return $geo;
    }
}
