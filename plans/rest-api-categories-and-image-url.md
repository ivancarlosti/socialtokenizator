# REST API Enhancements: Categories Endpoint & Image-by-URL

## Overview

Three enhancements:

1. **`GET /api/categories`** — New API endpoint to list all categories (no post counts)
2. **`POST /api/posts` with `image_url`** — Accept an image URL instead of file upload via API; server downloads and hosts the image locally (R2)
3. **Admin Upload page with `image_url`** — Same image-by-URL feature on the admin "Send Post" page, mirroring the API behavior

---

## Feature 1: `GET /api/categories`

### Motivation

Currently there is no API endpoint to retrieve the list of categories. API consumers (n8n, custom scripts) have no way to discover which categories exist before submitting a post.

### New File

**`app/Http/Controllers/Api/CategoryController.php`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('handle')->get();

        return response()->json([
            'data' => $categories->map(fn (Category $c) => [
                'id'         => $c->id,
                'handle'     => $c->handle,
                'name_en_US' => $c->name_en_US,
                'name_es_MX' => $c->name_es_MX,
                'name_pt_BR' => $c->name_pt_BR,
            ])->values(),
        ]);
    }
}
```

### Route Addition

**File: `routes/api.php`** — Add inside the existing `api.token` middleware group:

```php
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
```

### Response Format

```json
{
  "data": [
    {
      "id": 1,
      "handle": "landscape",
      "name_en_US": "Landscape",
      "name_es_MX": "Paisaje",
      "name_pt_BR": "Paisagem"
    },
    {
      "id": 2,
      "handle": "tech",
      "name_en_US": "Technology",
      "name_es_MX": "Tecnología",
      "name_pt_BR": "Tecnologia"
    }
  ]
}
```

> **Decision:** `images_count` is omitted — user only needs to know which categories exist, not how many posts each has.

---

## Feature 2: `POST /api/posts` with `image_url`

### Motivation

Currently the API requires a multipart file upload (`image` field). In automation workflows (n8n, Zapier), images often come from URLs. The user should be able to drop an image URL and have the server download and host it locally (on R2), avoiding hotlinking.

### Modified File

**`app/Http/Controllers/Api/PostController.php`** — The `store` method

### Validation Changes

**Before:**

```php
'image' => ['required', 'file', 'mimes:jpeg,png,webp,gif,avif', 'max:10240'],
```

**After:**

```php
'image'     => ['required_without:image_url', 'file', 'mimes:jpeg,png,webp,gif,avif', 'max:10240'],
'image_url' => ['required_without:image', 'string', 'url:http,https', 'max:2048'],
```

- `required_without` ensures at least one of `image` or `image_url` is present
- If both are sent, `image` (file upload) takes priority (existing behavior preserved first)
- `image_url` is validated as a proper HTTP/HTTPS URL

### Download Flow (when `image_url` is provided)

```
┌────────────────────────────────────────────────────────────┐
│  Client sends POST /api/posts with image_url               │
│  Content-Type: multipart/form-data or application/json     │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  1. Validation: image_url is present, is a valid URL       │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  2. HTTP GET the URL via Laravel Http facade                │
│     - Timeout: 15s connect, 30s total                      │
│     - User-Agent: SocialTokenizator/1.0                    │
└─────────────────────┬──────────────────────────────────────┘
                      │
              ┌───────┴────────┐
              │  HTTP status?  │
              └───┬────────┬───┘
         2xx │          │ non-2xx
             ▼           ▼
    ┌────────────┐  ┌──────────────────────────┐
    │ Continue   │  │ 422: "URL returned HTTP  │
    │            │  │       {status}"           │
    └─────┬──────┘  └──────────────────────────┘
          │
          ▼
┌────────────────────────────────────────────────────────────┐
│  3. Validate Content-Type header                           │
│     Allowed: image/jpeg, image/png, image/webp,            │
│              image/gif, image/avif                          │
│     If not: 422 "URL does not point to a valid image"      │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  4. Check Content-Length (if present) ≤ 10 MB              │
│     Also check actual body size after download             │
│     If exceeds: 422 "Image exceeds the 10 MB limit"        │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  5. Derive file extension from Content-Type                │
│     image/jpeg → .jpg    image/png → .png                  │
│     image/webp → .webp   image/gif → .gif                  │
│     image/avif → .avif                                     │
│                                                             │
│     Fallback: parse from URL path if Content-Type unclear  │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  6. Generate UUID, construct R2 key: images/{uuid}.{ext}  │
│     Storage::disk('r2')->put($r2Key, $body, [...]);       │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  7. Extract dimensions via getimagesizefromstring()        │
│     (works on the raw body bytes without temp file)        │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  8. Proceed with existing DB transaction:                  │
│     - Create Image record                                  │
│     - Sync categories (comma-separated handles/IDs)        │
│     - Sync tags                                            │
│     - Create sources                                       │
└─────────────────────┬──────────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────────┐
│  9. Return 201 with formatted post JSON                    │
└────────────────────────────────────────────────────────────┘
```

### Code Structure

The `store` method will be refactored to extract a private `processImage` method:

```php
/**
 * Process the image source: either from uploaded file or downloaded URL.
 * Returns an array with the image processing result.
 */
private function processImage(Request $request): array
{
    if ($request->hasFile('image')) {
        return $this->processFileUpload($request->file('image'));
    }

    return $this->processImageUrl($request->input('image_url'));
}

private function processFileUpload(UploadedFile $file): array
{
    $uuid = (string) Str::uuid();
    $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
    $r2Key = 'images/' . $uuid . '.' . $ext;

    Storage::disk('r2')->putFileAs('', $file, $r2Key, [
        'visibility' => 'public',
        'ContentType' => $file->getMimeType(),
    ]);

    [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

    return [
        'uuid'              => $uuid,
        'r2_key'            => $r2Key,
        'original_filename' => $file->getClientOriginalName(),
        'mime_type'         => $file->getMimeType(),
        'width'             => $width,
        'height'            => $height,
    ];
}

private function processImageUrl(string $url): array
{
    $response = Http::timeout(15)
        ->connectTimeout(15)
        ->withUserAgent('SocialTokenizator/1.0')
        ->get($url);

    if (! $response->successful()) {
        throw ValidationException::withMessages([
            'image_url' => ['The URL returned HTTP ' . $response->status() . '.'],
        ]);
    }

    $contentType = strtolower($response->header('Content-Type') ?? '');
    // Strip charset suffix (e.g., "image/jpeg; charset=binary")
    $contentType = explode(';', $contentType)[0];
    $contentType = trim($contentType);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
    if (! in_array($contentType, $allowedMimes, true)) {
        throw ValidationException::withMessages([
            'image_url' => ['The URL does not point to a valid image (detected: ' . $contentType . ').'],
        ]);
    }

    $body = $response->body();
    $size = strlen($body);

    if ($size > 10 * 1024 * 1024) { // 10 MB
        throw ValidationException::withMessages([
            'image_url' => ['The image at the URL exceeds the 10 MB size limit.'],
        ]);
    }

    $ext = match ($contentType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/avif' => 'avif',
        default      => 'jpg', // fallback
    };

    $uuid = (string) Str::uuid();
    $r2Key = 'images/' . $uuid . '.' . $ext;

    Storage::disk('r2')->put($r2Key, $body, [
        'visibility' => 'public',
        'ContentType' => $contentType,
    ]);

    [$width, $height] = @getimagesizefromstring($body) ?: [null, null];

    // Derive a filename from the URL path, or fallback to a generic name
    $path = parse_url($url, PHP_URL_PATH);
    $filename = $path ? basename($path) : 'image.' . $ext;
    // Ensure filename has the correct extension
    if (! str_ends_with(strtolower($filename), '.' . $ext)) {
        $filename .= '.' . $ext;
    }

    return [
        'uuid'              => $uuid,
        'r2_key'            => $r2Key,
        'original_filename' => $filename,
        'mime_type'         => $contentType,
        'width'             => $width,
        'height'            => $height,
    ];
}
```

### Error Matrix

| Condition | HTTP Status | Response |
|---|---|---|
| Neither `image` nor `image_url` provided | 422 | Standard Laravel validation: `{"image":["The image field is required when image url is not present."]}` |
| URL unreachable / DNS failure / timeout | 422 | `{"error":"Validation failed.","messages":{"image_url":["Failed to download image from the provided URL."]}}` |
| URL returns non-2xx | 422 | `{"messages":{"image_url":["The URL returned HTTP 404."]}}` |
| URL Content-Type is not an image | 422 | `{"messages":{"image_url":["The URL does not point to a valid image (detected: text/html)."]}}` |
| Downloaded body > 10 MB | 422 | `{"messages":{"image_url":["The image at the URL exceeds the 10 MB size limit."]}}` |
| Both `image` + `image_url` sent | 201 | `image` (file) wins; `image_url` is ignored |

### HTTP Client Exception Handling

Laravel's `Http` facade throws `Illuminate\Http\Client\ConnectionException` on network failures (timeout, DNS, etc.). These will be caught and converted to a validation error:

```php
try {
    $response = Http::timeout(15)->get($url);
} catch (\Illuminate\Http\Client\ConnectionException $e) {
    throw ValidationException::withMessages([
        'image_url' => ['Failed to download image from the provided URL.'],
    ]);
}
```

---

## Feature 3: Admin Upload Page — Image by URL

### Motivation

The admin "Send Post" page (`/admin/upload`) currently only accepts a file upload. The image-by-URL feature should also be available here, so admins can paste an image URL directly into the form.

### Modified Files

**`resources/views/admin/upload.blade.php`** — Add an `image_url` text input alongside the existing file input:

```blade
<div>
    <label class="block text-sm text-muted mb-1">{{ __('messages.upload_image_label') }}</label>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif,image/avif"
           class="block w-full text-sm text-muted">
    <p class="text-xs text-muted mt-1">— {{ __('messages.or') }} —</p>
    <input type="url" name="image_url" value="{{ old('image_url') }}"
           class="block w-full bg-input border border-input-border rounded px-3 py-2 text-sm text-copy mt-1"
           placeholder="https://example.com/photo.jpg">
</div>
```

**`app/Http/Controllers/Admin/UploadController.php`** — `store()` method refactored to support both sources, identical logic to the API controller.

### Validation Changes (Admin)

**Before:**

```php
'image' => ['required', 'file', 'mimes:jpeg,png,webp,gif,avif', 'max:10240'],
```

**After:**

```php
'image'     => ['required_without:image_url', 'file', 'mimes:jpeg,png,webp,gif,avif', 'max:10240'],
'image_url' => ['required_without:image', 'string', 'url:http,https', 'max:2048'],
```

### Shared Download Logic

To avoid duplicating the HTTP download code between `Api\PostController` and `Admin\UploadController`, the download logic will be extracted into a **trait** or a **service class**:

**New file: `app/Support/ImageUrlDownloader.php`** (or a trait on the base Controller)

This keeps both controllers DRY. Both will call the same `downloadImageFromUrl(string $url): array` method.

---

## Files Changed (Revised)

| File | Action | Description |
|---|---|---|
| `app/Http/Controllers/Api/CategoryController.php` | **Create** | New controller for categories API endpoint |
| `app/Support/ImageUrlDownloader.php` | **Create** | Shared trait/service for downloading images from URLs |
| `routes/api.php` | **Edit** | Add `GET /categories` route + import |
| `app/Http/Controllers/Api/PostController.php` | **Edit** | Accept `image_url` field; use shared downloader |
| `app/Http/Controllers/Admin/UploadController.php` | **Edit** | Accept `image_url` field in store(); use shared downloader |
| `resources/views/admin/upload.blade.php` | **Edit** | Add `image_url` text input field |
| `API.md` | **Edit** | Document `GET /api/categories` and `image_url` param |
| `README.md` | **Edit** | Update feature list to mention new capabilities |

No database changes, no new migrations, no new dependencies required.

---

## Security Considerations

1. **SSRF risk** — The server downloads from arbitrary user-supplied URLs. Mitigations:
   - Only HTTP/HTTPS schemes allowed (Laravel's `url:http,https` validation rule)
   - Timeout prevents hanging connections (15s)
   - Downloaded content is validated for MIME type before storage
   - API is behind bearer token auth; admin page is behind session auth (both admin-only)

2. **File size** — Enforced at 10 MB both via Content-Length check and post-download body size check.

3. **Malicious content** — Even if a URL returns `Content-Type: image/jpeg` but serves malicious content, the file is stored in R2 (not executed) and served with the stored Content-Type. No server-side image processing occurs beyond `getimagesizefromstring()`.

---

## Resolved Decisions

| # | Question | Decision |
|---|---|---|
| 1 | Include `images_count` in categories response? | **No** — user only needs category list, not post counts |
| 2 | Download timeout duration? | **15s connect, 15s total** |
| 3 | Extension from Content-Type vs URL path? | **Content-Type first**, URL path as fallback |
