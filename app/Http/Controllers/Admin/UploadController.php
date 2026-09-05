<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Source;
use App\Models\Tag;
use App\Rules\ImageFile;
use App\Support\ImageMime;
use App\Support\ImageUrlDownloader;
use App\Support\OgImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    use ImageUrlDownloader;

    public function create()
    {
        $categories = Category::orderBy('handle')->get();
        return view('admin.upload', compact('categories'));
    }

    public function store(Request $request)
    {
        // Strip empty source rows so the default JS row doesn't fail validation
        if ($request->has('sources') && is_array($request->input('sources'))) {
            $sources = array_filter($request->input('sources'), function (array $row): bool {
                $url = trim((string) ($row['url'] ?? ''));
                $label = trim((string) ($row['label'] ?? ''));
                return $url !== '' || $label !== '';
            });
            $request->merge(['sources' => array_values($sources) ?: null]);
        }

        $validated = $request->validate([
            'image'     => ['required_without:image_url', 'file', new ImageFile(['jpeg', 'png', 'webp', 'gif', 'avif']), 'max:32768'],
            'image_url' => ['nullable', 'required_without:image', 'string', 'url:http,https', 'max:2048'],
            'headline_en_US' => ['nullable', 'string', 'max:300'],
            'headline_es_MX' => ['nullable', 'string', 'max:300'],
            'headline_pt_BR' => ['nullable', 'string', 'max:300'],
            'description_en_US' => ['nullable', 'string', 'max:20000'],
            'description_es_MX' => ['nullable', 'string', 'max:20000'],
            'description_pt_BR' => ['nullable', 'string', 'max:20000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:500'],
            'sources' => ['nullable', 'array'],
            'sources.*.url' => ['nullable', 'url', 'max:1024'],
            'sources.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $authorId = $request->session()->get('admin_user_id');

        // Process image: prefer file upload, fall back to URL download
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
            $ext = ImageMime::extension($mime);
            $uuid = (string) Str::uuid();
            $r2Key = 'images/'.$uuid.'.'.$ext;

            Storage::disk('r2')->putFileAs('', $file, $r2Key, [
                'visibility' => 'public',
                'ContentType' => $mime,
            ]);

            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

            $imageMeta = [
                'uuid'              => $uuid,
                'r2_key'            => $r2Key,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $mime,
                'width'             => $width,
                'height'            => $height,
            ];
        } else {
            $imageMeta = $this->downloadImageFromUrl($validated['image_url']);
        }

        $image = DB::transaction(function () use ($imageMeta, $validated, $authorId) {
            $image = Image::create([
                'uuid'              => $imageMeta['uuid'],
                'author_id'         => $authorId,
                'r2_key'            => $imageMeta['r2_key'],
                'original_filename' => $imageMeta['original_filename'],
                'mime_type'         => $imageMeta['mime_type'],
                'width'             => $imageMeta['width'],
                'height'            => $imageMeta['height'],
                'headline_en_US' => $validated['headline_en_US'] ?? null,
                'headline_es_MX' => $validated['headline_es_MX'] ?? null,
                'headline_pt_BR' => $validated['headline_pt_BR'] ?? null,
                'description_en_US' => $validated['description_en_US'] ?? null,
                'description_es_MX' => $validated['description_es_MX'] ?? null,
                'description_pt_BR' => $validated['description_pt_BR'] ?? null,
            ]);

            // Sync categories
            if (! empty($validated['categories'])) {
                $image->categories()->sync($validated['categories']);
            }

            // Sync tags
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

            // Sources
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

        $this->generateOgThumbnail($image);

        return redirect()->route('image.show', ['slug' => $image->short_id])
            ->with('status', 'Image uploaded.');
    }

    public function edit(string $uuid)
    {
        $image = Image::with(['categories', 'tags', 'sources'])->where('uuid', $uuid)->firstOrFail();
        $categories = Category::orderBy('handle')->get();

        return view('admin.edit', [
            'image'      => $image,
            'categories' => $categories,
            'tagList'    => $image->tags->pluck('name')->implode(', '),
            'selectedCategories' => $image->categories->pluck('id')->toArray(),
        ]);
    }

    public function update(Request $request, string $uuid)
    {
        $image = Image::where('uuid', $uuid)->firstOrFail();

        // Strip empty source rows so the default JS row doesn't fail validation
        if ($request->has('sources') && is_array($request->input('sources'))) {
            $sources = array_filter($request->input('sources'), function (array $row): bool {
                $url = trim((string) ($row['url'] ?? ''));
                $label = trim((string) ($row['label'] ?? ''));
                return $url !== '' || $label !== '';
            });
            $request->merge(['sources' => array_values($sources) ?: null]);
        }

        $validated = $request->validate([
            'image'     => ['nullable', 'file', new ImageFile(['jpeg', 'png', 'webp', 'gif', 'avif']), 'max:32768'],
            'image_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'headline_en_US' => ['nullable', 'string', 'max:300'],
            'headline_es_MX' => ['nullable', 'string', 'max:300'],
            'headline_pt_BR' => ['nullable', 'string', 'max:300'],
            'description_en_US' => ['nullable', 'string', 'max:20000'],
            'description_es_MX' => ['nullable', 'string', 'max:20000'],
            'description_pt_BR' => ['nullable', 'string', 'max:20000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'string', 'max:500'],
            'sources' => ['nullable', 'array'],
            'sources.*.url' => ['nullable', 'url', 'max:1024'],
            'sources.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $replaced = false;
        $oldOgKey = $image->og_image_key;

        DB::transaction(function () use ($image, $validated, $request, &$replaced) {
            // ── Image replacement ──
            if ($request->hasFile('image')) {
                $replaced = true;
                $oldR2Key = $image->r2_key;

                $file = $request->file('image');
                $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
                $ext = ImageMime::extension($mime);
                $uuid = (string) Str::uuid();
                $r2Key = 'images/' . $uuid . '.' . $ext;

                Storage::disk('r2')->putFileAs('', $file, $r2Key, [
                    'visibility'  => 'public',
                    'ContentType' => $mime,
                ]);

                [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

                $image->update([
                    'uuid'              => $uuid,
                    'r2_key'            => $r2Key,
                    'og_image_key'      => null,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type'         => $mime,
                    'width'             => $width,
                    'height'            => $height,
                ]);

                // Delete old image from R2 after successful replacement
                Storage::disk('r2')->delete($oldR2Key);
            } elseif (! empty($validated['image_url'])) {
                $replaced = true;
                $oldR2Key = $image->r2_key;

                $imageMeta = $this->downloadImageFromUrl($validated['image_url']);

                $image->update([
                    'uuid'              => $imageMeta['uuid'],
                    'r2_key'            => $imageMeta['r2_key'],
                    'og_image_key'      => null,
                    'original_filename' => $imageMeta['original_filename'],
                    'mime_type'         => $imageMeta['mime_type'],
                    'width'             => $imageMeta['width'],
                    'height'            => $imageMeta['height'],
                ]);

                Storage::disk('r2')->delete($oldR2Key);
            }

            // ── Metadata update ──
            $image->update([
                'headline_en_US' => $validated['headline_en_US'] ?? null,
                'headline_es_MX' => $validated['headline_es_MX'] ?? null,
                'headline_pt_BR' => $validated['headline_pt_BR'] ?? null,
                'description_en_US' => $validated['description_en_US'] ?? null,
                'description_es_MX' => $validated['description_es_MX'] ?? null,
                'description_pt_BR' => $validated['description_pt_BR'] ?? null,
            ]);

            // Sync categories
            $image->categories()->sync($validated['categories'] ?? []);

            // Sync tags
            $tagIds = [];
            foreach (explode(',', (string) ($validated['tags'] ?? '')) as $rawTag) {
                $name = Tag::normalize($rawTag);
                if ($name === '') continue;
                $tag = Tag::firstOrCreate(['name' => $name]);
                $tagIds[$tag->id] = true;
            }
            $image->tags()->sync(array_keys($tagIds));

            // Recreate sources
            $image->sources()->delete();
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
        });

        if ($replaced) {
            if ($oldOgKey) {
                Storage::disk('r2')->delete($oldOgKey);
            }
            $this->generateOgThumbnail($image);
        }

        return redirect()->route('image.show', ['slug' => $image->short_id])
            ->with('status', __('messages.image_updated'));
    }

    public function destroy(string $uuid)
    {
        $image = Image::where('uuid', $uuid)->firstOrFail();
        Storage::disk('r2')->delete($image->r2_key);
        if ($image->og_image_key) {
            Storage::disk('r2')->delete($image->og_image_key);
        }
        $image->delete();
        return redirect()->route('home')->with('status', 'Image deleted.');
    }

    private function generateOgThumbnail(Image $image): void
    {
        $key = OgImageProcessor::generate($image);
        if ($key !== null) {
            $image->update(['og_image_key' => $key]);
        }
    }
}
