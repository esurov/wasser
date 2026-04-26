<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ViennaOpenData
{
    public const FOUNTAINS = 'TRINKBRUNNENOGD';

    public const TOILETS = 'WCANLAGEOGD';

    public const LAYERS = [self::FOUNTAINS, self::TOILETS];

    /**
     * Cached lean feature list for the given layer. Populated on first hit and
     * refreshed daily by the `vienna:refresh` command.
     *
     * @return array<int, array{lat: float, lon: float, properties: array<string, mixed>}>
     */
    public function features(string $layer): array
    {
        return Cache::rememberForever($this->cacheKey($layer), fn () => $this->fetch($layer));
    }

    /**
     * Fetch fresh data and overwrite the cache only when the payload changed.
     * Returns true if the cache was updated.
     */
    public function refresh(string $layer): bool
    {
        $features = $this->fetch($layer);
        $hash = sha1(json_encode($features, JSON_THROW_ON_ERROR));

        $hashKey = $this->cacheKey($layer).'.hash';
        if (Cache::get($hashKey) === $hash) {
            return false;
        }

        Cache::forever($this->cacheKey($layer), $features);
        Cache::forever($hashKey, $hash);

        return true;
    }

    private function cacheKey(string $layer): string
    {
        return "vienna.{$layer}";
    }

    /**
     * @return array<int, array{lat: float, lon: float, properties: array<string, mixed>}>
     */
    private function fetch(string $layer): array
    {
        $response = Http::timeout(30)->get('https://data.wien.gv.at/daten/geo', [
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => "ogdwien:{$layer}",
            'srsName' => 'EPSG:4326',
            'outputFormat' => 'json',
        ])->throw();

        // Vienna WFS sometimes returns HTTP 200 with an XML ExceptionReport body
        // (e.g. connection pool exhausted upstream). Reject anything that isn't
        // a JSON FeatureCollection so we don't cache an empty payload forever.
        $payload = $response->json();
        if (! is_array($payload) || ! isset($payload['features']) || ! is_array($payload['features'])) {
            throw new RuntimeException("Vienna Open Data response for {$layer} was not a valid FeatureCollection.");
        }

        return array_values(array_filter(array_map(function (array $feature): ?array {
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                return null;
            }

            return [
                'lon' => (float) $coords[0],
                'lat' => (float) $coords[1],
                'properties' => $feature['properties'] ?? [],
            ];
        }, $payload['features'])));
    }
}
