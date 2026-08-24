<?php

namespace App\Http\Controllers;

use App\Models\Image;

class ImageController extends Controller
{
    public function show(string $slug)
    {
        $image = Image::with(['categories', 'tags', 'sources'])
            ->where('short_id', $slug)
            ->first();

        // Legacy fallback: posts previously lived at /p/{uuid}.
        if (! $image) {
            $image = Image::with(['categories', 'tags', 'sources'])
                ->where('uuid', $slug)
                ->firstOrFail();
        }

        return view('image.show', compact('image'));
    }
}
