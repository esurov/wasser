<?php

namespace App\Http\Controllers;

use App\Models\FountainPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FountainPhotoController extends Controller
{
    public function index(int $objectId): JsonResponse
    {
        $photos = FountainPhoto::query()
            ->where('object_id', $objectId)
            ->orderByDesc('id')
            ->get(['id', 'object_id', 'path']);

        return response()->json(['data' => $photos]);
    }

    public function store(Request $request, int $objectId): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['photo']->store("fountain_photos/{$objectId}", 'public');

        $photo = FountainPhoto::create([
            'object_id' => $objectId,
            'path' => $path,
        ]);

        return response()->json(['data' => $photo], 201);
    }
}
