<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Source;
use App\Models\Tag;
use App\Rules\ImageFile;
use App\Support\ImageMime;
use App\Support\ImageUrlDownloader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    use ImageUrlDownloader;

    /**
     * Create a new post with image upload or image URL.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'image'     => ['required_without:image_url', 'file', new ImageFile(['jpeg', 'png', 'webp', 'gif', 'avif']), 'max:10240'],
                'image_url' => ['nullable', 'required_without:image', 'string', 'url:http,https', 'max:2048'],
                'headline_en_US' => ['nullable', 'string', 'max:300'],
                'headline_es_MX' => ['nullable', 'string', 'max:300'],
                'headline_pt_BR' => ['nullable', 'string', 'max:300'],
                'description_en_US' => ['nullable', 'string', 'max:5000'],
                'description_es_MX' => ['nullable', 'string', 'max:5000'],
                'description_pt_BR' => ['nullable', 'string', 'max:5000'],
                'categories' => ['nullable', 'string'],
                'tags' => ['nullable', 'string', 'max:500'],
                'sources' => ['nullable', 'json'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'messages' => $e->errors(),
            ], 422);
        }

        // Process image: prefer file upload, fall back to URL download
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
            $ext = ImageMime::extension($mime);
            $uuid = (string) Str::uuid();
            $r2Key = 'images/' . $uuid . '.' . $ext;

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

        $image = DB::transaction(function () use ($imageMeta, $validated) {
            $image = Image::create([
                'uuid'              => $imageMeta['uuid'],
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

            // Sync categories — accept comma-separated handles or IDs
            $categoryInput = trim((string) ($validated['categories'] ?? ''));
            if ($categoryInput !== '') {
                $categoryIds = $this->resolveCategories($categoryInput);
                if ($categoryIds) {
                    $image->categories()->sync($categoryIds);
                }
            }

            // Sync tags
            $tagInput = trim((string) ($validated['tags'] ?? ''));
            if ($tagInput !== '') {
                $tagIds = [];
                foreach (explode(',', $tagInput) as $rawTag) {
                    $name = Tag::normalize($rawTag);
                    if ($name === '') {
                        continue;
                    }
                    $tag = Tag::firstOrCreate(['name' => $name]);
                    $tagIds[$tag->id] = true;
                }
                if ($tagIds) {
                    $image->tags()->sync(array_keys($tagIds));
                }
            }

            // Sources (JSON array of {url, label?})
            $sourcesInput = trim((string) ($validated['sources'] ?? ''));
            if ($sourcesInput !== '') {
                $sources = json_decode($sourcesInput, true);
                if (is_array($sources)) {
                    $position = 0;
                    foreach ($sources as $row) {
                        $url = trim((string) ($row['url'] ?? ''));
                        if ($url === '') {
                            continue;
                        }
                        Source::create([
                            'image_id' => $image->id,
                            'url' => $url,
                            'label' => trim((string) ($row['label'] ?? '')) ?: null,
                            'position' => $position++,
                        ]);
                    }
                }
            }

            return $image;
        });

        $image->load(['categories', 'tags', 'sources']);

        return response()->json($this->formatPost($image), 201);
    }

    /**
     * List posts with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $limit = (int) $request->query('limit');

        $query = Image::query()->with(['categories', 'tags', 'sources']);

        // Filter by category handle
        $categoryHandle = trim((string) $request->query('category', ''));
        if ($categoryHandle !== '') {
            $query->whereHas('categories', function ($q) use ($categoryHandle) {
                $q->where('handle', $categoryHandle);
            });
        }

        // Filter by tag slug
        $tagFilter = trim((string) $request->query('tag', ''));
        if ($tagFilter !== '') {
            $normalized = Tag::normalize($tagFilter);
            $query->whereHas('tags', function ($q) use ($normalized) {
                $q->where('name', $normalized);
            });
        }

        // Word search across headlines and descriptions
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('headline_en_US', 'like', '%' . $search . '%')
                    ->orWhere('headline_es_MX', 'like', '%' . $search . '%')
                    ->orWhere('headline_pt_BR', 'like', '%' . $search . '%')
                    ->orWhere('description_en_US', 'like', '%' . $search . '%')
                    ->orWhere('description_es_MX', 'like', '%' . $search . '%')
                    ->orWhere('description_pt_BR', 'like', '%' . $search . '%')
                    ->orWhereHas('tags', function ($t) use ($search) {
                        $t->where('name', Tag::normalize($search));
                    });
            });
        }

        // Sort order
        $sort = $request->query('sort', 'latest');
        if ($sort === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        // If limit is provided, use simple limit without pagination metadata
        if ($limit > 0) {
            $limit = min($limit, 1000);
            $images = $query->limit($limit)->get();

            return response()->json([
                'data' => $images->map(fn (Image $img) => $this->formatPost($img)),
                'count' => $images->count(),
                'limit' => $limit,
            ]);
        }

        // Paginated response
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->map(fn (Image $img) => $this->formatPost($img)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show a single post by UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        $image = Image::with(['categories', 'tags', 'sources'])->where('uuid', $uuid)->first();

        if (! $image) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        return response()->json($this->formatPost($image));
    }

    /**
     * Delete a post by UUID.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $image = Image::where('uuid', $uuid)->first();

        if (! $image) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        Storage::disk('r2')->delete($image->r2_key);
        $image->delete();

        return response()->json(null, 204);
    }

    /**
     * Update a post by UUID — metadata and optionally the image (file or URL).
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $image = Image::where('uuid', $uuid)->first();

        if (! $image) {
            return response()->json(['error' => 'Post not found.'], 404);
        }

        try {
            $validated = $request->validate([
                'image'     => ['nullable', 'file', new ImageFile(['jpeg', 'png', 'webp', 'gif', 'avif']), 'max:10240'],
                'image_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
                'headline_en_US' => ['nullable', 'string', 'max:300'],
                'headline_es_MX' => ['nullable', 'string', 'max:300'],
                'headline_pt_BR' => ['nullable', 'string', 'max:300'],
                'description_en_US' => ['nullable', 'string', 'max:5000'],
                'description_es_MX' => ['nullable', 'string', 'max:5000'],
                'description_pt_BR' => ['nullable', 'string', 'max:5000'],
                'categories' => ['nullable', 'string'],
                'tags' => ['nullable', 'string', 'max:500'],
                'sources' => ['nullable', 'json'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'messages' => $e->errors(),
            ], 422);
        }

        DB::transaction(function () use ($image, $validated, $request) {
            // ── Image replacement ──
            if ($request->hasFile('image')) {
                $oldR2Key = $image->r2_key;

                $file = $request->file('image');
                $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
                $ext = ImageMime::extension($mime);
                $newUuid = (string) Str::uuid();
                $r2Key = 'images/' . $newUuid . '.' . $ext;

                Storage::disk('r2')->putFileAs('', $file, $r2Key, [
                    'visibility'  => 'public',
                    'ContentType' => $mime,
                ]);

                [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

                $image->update([
                    'uuid'              => $newUuid,
                    'r2_key'            => $r2Key,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type'         => $mime,
                    'width'             => $width,
                    'height'            => $height,
                ]);

                Storage::disk('r2')->delete($oldR2Key);
            } elseif (! empty($validated['image_url'])) {
                $oldR2Key = $image->r2_key;

                $imageMeta = $this->downloadImageFromUrl($validated['image_url']);

                $image->update([
                    'uuid'              => $imageMeta['uuid'],
                    'r2_key'            => $imageMeta['r2_key'],
                    'original_filename' => $imageMeta['original_filename'],
                    'mime_type'         => $imageMeta['mime_type'],
                    'width'             => $imageMeta['width'],
                    'height'            => $imageMeta['height'],
                ]);

                Storage::disk('r2')->delete($oldR2Key);
            }

            // ── Metadata update ──
            $image->update(array_filter([
                'headline_en_US' => $validated['headline_en_US'] ?? null,
                'headline_es_MX' => $validated['headline_es_MX'] ?? null,
                'headline_pt_BR' => $validated['headline_pt_BR'] ?? null,
                'description_en_US' => $validated['description_en_US'] ?? null,
                'description_es_MX' => $validated['description_es_MX'] ?? null,
                'description_pt_BR' => $validated['description_pt_BR'] ?? null,
            ], fn ($v) => $v !== null));

            // Sync categories
            $categoryInput = trim((string) ($validated['categories'] ?? ''));
            if ($categoryInput !== '') {
                $categoryIds = $this->resolveCategories($categoryInput);
                if ($categoryIds) {
                    $image->categories()->sync($categoryIds);
                }
            }

            // Sync tags
            $tagInput = trim((string) ($validated['tags'] ?? ''));
            if ($tagInput !== '') {
                $tagIds = [];
                foreach (explode(',', $tagInput) as $rawTag) {
                    $name = Tag::normalize($rawTag);
                    if ($name === '') {
                        continue;
                    }
                    $tag = Tag::firstOrCreate(['name' => $name]);
                    $tagIds[$tag->id] = true;
                }
                if ($tagIds) {
                    $image->tags()->sync(array_keys($tagIds));
                }
            }

            // Sources (JSON array)
            $sourcesInput = trim((string) ($validated['sources'] ?? ''));
            if ($sourcesInput !== '') {
                $image->sources()->delete();
                $sources = json_decode($sourcesInput, true);
                if (is_array($sources)) {
                    $position = 0;
                    foreach ($sources as $row) {
                        $url = trim((string) ($row['url'] ?? ''));
                        if ($url === '') {
                            continue;
                        }
                        Source::create([
                            'image_id' => $image->id,
                            'url'      => $url,
                            'label'    => trim((string) ($row['label'] ?? '')) ?: null,
                            'position' => $position++,
                        ]);
                    }
                }
            }
        });

        $image->load(['categories', 'tags', 'sources']);

        return response()->json($this->formatPost($image));
    }

    /**
     * Format a post for JSON response.
     */
    private function formatPost(Image $image): array
    {
        return [
            'uuid' => $image->uuid,
            'public_url' => $image->public_url,
            'original_filename' => $image->original_filename,
            'mime_type' => $image->mime_type,
            'width' => $image->width,
            'height' => $image->height,
            'headline_en_US' => $image->headline_en_US,
            'headline_es_MX' => $image->headline_es_MX,
            'headline_pt_BR' => $image->headline_pt_BR,
            'description_en_US' => $image->description_en_US,
            'description_es_MX' => $image->description_es_MX,
            'description_pt_BR' => $image->description_pt_BR,
            'categories' => $image->categories->map(fn (Category $c) => [
                'id' => $c->id,
                'handle' => $c->handle,
                'name_en_US' => $c->name_en_US,
                'name_es_MX' => $c->name_es_MX,
                'name_pt_BR' => $c->name_pt_BR,
            ])->values(),
            'tags' => $image->tags->map(fn (Tag $t) => [
                'id' => $t->id,
                'name' => $t->name,
            ])->values(),
            'sources' => $image->sources->map(fn (Source $s) => [
                'id' => $s->id,
                'label' => $s->label,
                'url' => $s->url,
                'position' => $s->position,
            ])->values(),
            'created_at' => $image->created_at->toIso8601String(),
            'updated_at' => $image->updated_at->toIso8601String(),
        ];
    }

    /**
     * Resolve comma-separated category values which can be IDs (integers) or handles (strings).
     */
    private function resolveCategories(string $input): array
    {
        $parts = array_map('trim', explode(',', $input));
        $ids = [];
        $handles = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (is_numeric($part)) {
                $ids[] = (int) $part;
            } else {
                $handles[] = $part;
            }
        }

        $resolved = [];

        if ($ids) {
            $existingIds = Category::whereIn('id', $ids)->pluck('id')->toArray();
            $resolved = array_merge($resolved, $existingIds);
        }

        if ($handles) {
            $handleIds = Category::whereIn('handle', $handles)->pluck('id')->toArray();
            $resolved = array_merge($resolved, $handleIds);
        }

        return array_unique($resolved);
    }
}
