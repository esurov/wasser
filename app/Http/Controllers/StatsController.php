<?php

namespace App\Http\Controllers;

use App\Models\FountainPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StatsController extends Controller
{
    /**
     * English translations for the `BASIS_TYP_TXT` category names used by
     * Vienna Open Data's TRINKBRUNNENOGD feature type.
     */
    private const CATEGORY_EN = [
        'Trinkbrunnen' => 'Drinking fountain',
        'Trinkbrunnen mit Tränke' => 'Drinking fountain with trough',
        'Trinkhydrant' => 'Drinking hydrant',
        'Trinkhydrant mit Tränke' => 'Drinking hydrant with trough',
        'Auslaufbrunnen' => 'Spout fountain',
        'Auslaufbrunnen mit Tränke' => 'Spout fountain with trough',
        'Sprühnebeldusche' => 'Misting shower',
        'Spielbrunnen' => 'Play fountain',
        'Zierbrunnen' => 'Ornamental fountain',
        'Mobiler Trinkbrunnen mit Sprühnebelfunktion' => 'Mobile drinking fountain with mist',
        'Wasserspielmöglichkeit' => 'Water play feature',
        'Bodenfontäne' => 'Ground fountain',
        'Grundwasserbrunnen' => 'Groundwater well',
        'ESC-Brunnen' => 'ESC fountain',
        'Hundetrinkbrunnen' => 'Dog drinking fountain',
    ];

    public function __invoke(): View
    {
        $stats = Cache::remember('stats.overview', now()->addHour(), function () {
            $fountains = $this->fetchVienna('TRINKBRUNNENOGD');
            $toilets = $this->fetchVienna('WCANLAGEOGD');

            $hashesWithPhotos = FountainPhoto::query()
                ->distinct()
                ->pluck('shape_hash')
                ->flip();

            $fountainsWithPhotos = 0;
            $counts = [];

            foreach ($fountains as $fountain) {
                $category = $fountain['properties']['BASIS_TYP_TXT'] ?? 'Unknown';
                $counts[$category] = ($counts[$category] ?? 0) + 1;

                if (isset($hashesWithPhotos[$this->shapeHash($fountain)])) {
                    $fountainsWithPhotos++;
                }
            }

            arsort($counts);

            $categories = [];
            foreach ($counts as $name => $count) {
                $categories[] = [
                    'name' => $name,
                    'name_en' => self::CATEGORY_EN[$name] ?? null,
                    'count' => $count,
                ];
            }

            return [
                'fountains_total' => count($fountains),
                'fountains_with_photos' => $fountainsWithPhotos,
                'categories' => $categories,
                'toilets_total' => count($toilets),
            ];
        });

        return view('stats', $stats);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchVienna(string $typeName): array
    {
        $response = Http::timeout(20)->get('https://data.wien.gv.at/daten/geo', [
            'service' => 'WFS',
            'version' => '1.1.0',
            'request' => 'GetFeature',
            'typeName' => "ogdwien:{$typeName}",
            'srsName' => 'EPSG:4326',
            'outputFormat' => 'json',
        ])->throw();

        return $response->json('features') ?? [];
    }

    /**
     * Must match the client-side `shapeKey` hash in map.blade.php:
     * SHA-1 of the `SHAPE` property when present, otherwise of
     * `"${lat.toFixed(7)},${lon.toFixed(7)}"` from the geometry.
     *
     * @param  array<string, mixed>  $feature
     */
    private function shapeHash(array $feature): string
    {
        $shape = $feature['properties']['SHAPE'] ?? null;
        if ($shape !== null && $shape !== '') {
            return sha1((string) $shape);
        }

        [$lon, $lat] = $feature['geometry']['coordinates'] ?? [0, 0];

        return sha1(number_format((float) $lat, 7, '.', '').','.number_format((float) $lon, 7, '.', ''));
    }
}
