<?php

namespace App\Http\Controllers;

use App\Services\ViennaOpenData;
use Illuminate\Http\JsonResponse;

class ViennaOpenDataController extends Controller
{
    public function __construct(private ViennaOpenData $vienna) {}

    public function fountains(): JsonResponse
    {
        return $this->respond(ViennaOpenData::FOUNTAINS);
    }

    public function toilets(): JsonResponse
    {
        return $this->respond(ViennaOpenData::TOILETS);
    }

    private function respond(string $layer): JsonResponse
    {
        return response()
            ->json(['features' => $this->vienna->features($layer)])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
