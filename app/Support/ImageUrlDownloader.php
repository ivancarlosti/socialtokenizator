<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        return ImageMime::POST_TYPES;
    }

    /**
     * Map a MIME type to a file extension.
     */
    protected function extensionFromMime(string $mime): string
    {
        return ImageMime::extension($mime);
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

        $contentTypeHeader = (string) $response->header('Content-Type');
        $contentType = strtolower(trim(explode(';', $contentTypeHeader)[0]));

        $body = $response->body();

        // Prefer magic-byte sniffing over the header: many hosts serve AVIF
        // (and sometimes WebP) as application/octet-stream.
        $mime = ImageMime::resolve($body, $contentTypeHeader, $this->extensionFromUrl($url));

        Log::info('ImageUrlDownloader: validating downloaded image', [
            'url'                     => $url,
            'content_type_header'     => $contentTypeHeader,
            'normalized_content_type' => $contentType,
            'resolved_mime'           => $mime,
            'first_bytes_hex'         => bin2hex(substr($body, 0, 16)),
        ]);

        if ($mime === null || ! in_array($mime, $this->allowedImageMimes(), true)) {
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_not_image', [
                    'type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                ])],
            ]);
        }

        $size = strlen($body);

        if ($size > 10 * 1024 * 1024) { // 10 MB
            throw ValidationException::withMessages([
                'image_url' => [__('messages.image_url_too_large')],
            ]);
        }

        $ext = $this->extensionFromMime($mime);
        $uuid = (string) Str::uuid();
        $r2Key = 'images/' . $uuid . '.' . $ext;

        Storage::disk('r2')->put($r2Key, $body, [
            'visibility' => 'public',
            'ContentType' => $mime,
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
            'mime_type'         => $mime,
            'width'             => $width,
            'height'            => $height,
        ];
    }

    /**
     * Extract the file extension from the URL path, if any.
     */
    protected function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return $ext !== '' ? $ext : null;
    }
}
