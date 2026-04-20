<?php

namespace App\Http\Controllers;

use App\Models\FountainPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FountainPhotoController extends Controller
{
    public function index(string $shapeHash): JsonResponse
    {
        $photos = FountainPhoto::query()
            ->where('shape_hash', $shapeHash)
            ->orderByDesc('id')
            ->get(['id', 'shape_hash', 'path']);

        return response()->json(['data' => $photos]);
    }

    public function store(Request $request, string $shapeHash): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['photo']->store("fountain_photos/{$shapeHash}", 'public');

        $photo = FountainPhoto::create([
            'shape_hash' => $shapeHash,
            'path' => $path,
        ]);

        return response()->json(['data' => $photo], 201);
    }
}
