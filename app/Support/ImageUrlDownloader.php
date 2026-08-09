<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Trait providing server-side image download from a URL and storage to R2.
 *
 * Used by both Api\PostController and Admin\UploadController to avoid
 * duplicating the download-and-validate logic.
 */
trait ImageUrlDownloader
{
    /**
     * Allowed image MIME types.
     */
    protected function allowedImageMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
    }

    /**
     * Map a MIME type to a file extension.
     */
    protected function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/avif' => 'avif',
            default      => 'jpg',
        };
    }

    /**
     * Download an image from a URL, validate it, and store it to R2.
     *
     * @param  string  $url  The publicly-accessible image URL.
     * @return array{ uuid: string, r2_key: string, original_filename: string, mime_type: string, width: int|null, height: int|null }
     *
     * @throws ValidationException  On download failure, bad content type, or oversize.
     */
    protected function downloadImageFromUrl(string $url): array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(15)
                ->withUserAgent('SocialTokenizator/1.0')
                ->get($url);
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_download_failed')],
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_http_error', ['code' => $response->status()])],
            ]);
        }

        // Parse Content-Type — strip charset and other params
        $contentType = strtolower((string) $response->header('Content-Type'));
        $contentType = trim(explode(';', $contentType)[0]);

        if (! in_array($contentType, $this->allowedImageMimes(), true)) {
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_not_image', ['type' => $contentType])],
            ]);
        }

        $body = $response->body();
        $size = strlen($body);

        if ($size > 10 * 1024 * 1024) { // 10 MB
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_too_large')],
            ]);
        }

        $ext = $this->extensionFromMime($contentType);
        $uuid = (string) Str::uuid();
        $r2Key = 'images/' . $uuid . '.' . $ext;

        Storage::disk('r2')->put($r2Key, $body, [
            'visibility' => 'public',
            'ContentType' => $contentType,
        ]);

        [$width, $height] = @getimagesizefromstring($body) ?: [null, null];

        // Derive a filename from the URL path, or fallback
        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = $urlPath ? basename($urlPath) : 'image.' . $ext;
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
}
