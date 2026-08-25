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

        if ($image) {
            return view('image.show', compact('image'));
        }

        // Legacy fallback: posts previously lived at /p/{uuid}.
        // Redirect to the canonical /p/{short_id} URL.
        $image = Image::with(['categories', 'tags', 'sources'])
            ->where('uuid', $slug)
            ->first();

        if (! $image) {
            abort(404);
        }

        return redirect()->route('image.show', ['slug' => $image->short_id], 301);
    }
}
