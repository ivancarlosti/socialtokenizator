<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Source;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function create()
    {
        return view('admin.upload');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:500'],
            'sources' => ['nullable', 'array'],
            'sources.*.url' => ['nullable', 'url', 'max:1024'],
            'sources.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('image');
        $uuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $r2Key = 'images/'.$uuid.'.'.$ext;

        Storage::disk('r2')->putFileAs('', $file, $r2Key, [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType(),
        ]);

        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $image = DB::transaction(function () use ($uuid, $r2Key, $file, $width, $height, $validated) {
            $image = Image::create([
                'uuid' => $uuid,
                'r2_key' => $r2Key,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'width' => $width,
                'height' => $height,
                'description' => $validated['description'] ?? null,
            ]);

            $tagIds = [];
            foreach (explode(',', (string) ($validated['tags'] ?? '')) as $rawTag) {
                $name = Tag::normalize($rawTag);
                if ($name === '') continue;
                $tag = Tag::firstOrCreate(['name' => $name]);
                $tagIds[$tag->id] = true;
            }
            if ($tagIds) {
                $image->tags()->sync(array_keys($tagIds));
            }

            $position = 0;
            foreach ($validated['sources'] ?? [] as $row) {
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') continue;
                Source::create([
                    'image_id' => $image->id,
                    'url' => $url,
                    'label' => trim((string) ($row['label'] ?? '')) ?: null,
                    'position' => $position++,
                ]);
            }

            return $image;
        });

        return redirect()->route('image.show', ['uuid' => $image->uuid])
            ->with('status', 'Image uploaded.');
    }

    public function destroy(string $uuid)
    {
        $image = Image::where('uuid', $uuid)->firstOrFail();
        Storage::disk('r2')->delete($image->r2_key);
        $image->delete();
        return redirect()->route('home')->with('status', 'Image deleted.');
    }
}
