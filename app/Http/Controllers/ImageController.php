<?php

namespace App\Http\Controllers;

use App\Models\Image;

class ImageController extends Controller
{
    public function show(string $uuid)
    {
        $image = Image::with(['categories', 'tags', 'sources'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return view('image.show', compact('image'));
    }
}
