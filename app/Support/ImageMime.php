<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Centralized image MIME detection.
 *
 * Detection does not rely solely on Content-Type headers or finfo/libmagic,
 * both of which frequently report AVIF (and sometimes WebP/ICO) as
 * application/octet-stream. Detection priority is: magic bytes, then a
 * recognized header value, then the file/URL extension.
 */
final class ImageMime
{
    /**
     * Image MIME types accepted for posts (file upload and URL download).
     */
    public const POST_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    /**
     * Additional MIME types accepted for branding assets (logo / favicon).
     */
    public const BRANDING_TYPES = [
        'image/svg+xml',
        'image/x-icon',
    ];

    /**
     * Map a MIME type to a file extension.
     */
    public static function extension(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            'image/svg+xml' => 'svg',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => 'jpg',
        };
    }

    /**
     * Map a file extension to an image MIME type.
     */
    public static function fromExtension(string $extension): ?string
    {
        return match (strtolower(ltrim($extension, '.'))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => null,
        };
    }

    /**
     * Detect an image MIME type from raw bytes (magic-byte sniffing).
     */
    public static function sniff(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }

        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        // ISO-BMFF based formats: AVIF uses the "avif" or "avis" brand inside
        // the ftyp box (bytes 4-12).
        if (strlen($bytes) >= 12 && substr($bytes, 4, 4) === 'ftyp') {
            $brand = substr($bytes, 8, 4);
            if ($brand === 'avif' || $brand === 'avis') {
                return 'image/avif';
            }
        }

        // ICO / CUR (Windows icon/cursor).
        if (str_starts_with($bytes, "\x00\x00\x01\x00") || str_starts_with($bytes, "\x00\x00\x02\x00")) {
            return 'image/x-icon';
        }

        // SVG is XML-based; scan a short prefix for the root element.
        if (str_contains(strtolower(substr($bytes, 0, 512)), '<svg')) {
            return 'image/svg+xml';
        }

        return null;
    }

    /**
     * Resolve the final MIME type for an image payload.
     *
     * Priority: magic bytes, then a recognized header value, then extension.
     */
    public static function resolve(string $bytes, ?string $headerMime = null, ?string $extension = null): ?string
    {
        $sniffed = self::sniff($bytes);
        if ($sniffed !== null) {
            return $sniffed;
        }

        $header = self::normalizeHeader($headerMime);
        if ($header !== null) {
            return $header;
        }

        if ($extension !== null) {
            return self::fromExtension($extension);
        }

        return null;
    }

    /**
     * Detect the MIME type of an uploaded file without trusting libmagic.
     */
    public static function ofUploadedFile(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $head = fread($handle, 4096);
        fclose($handle);

        if ($head === false || $head === '') {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension();

        return self::sniff($head)
            ?? self::normalizeHeader($file->getMimeType())
            ?? self::fromExtension((string) $extension);
    }

    /**
     * Normalize a Content-Type header value to an allowed image MIME, or null.
     */
    public static function normalizeHeader(?string $headerMime): ?string
    {
        if ($headerMime === null || $headerMime === '') {
            return null;
        }

        $mime = strtolower(trim(explode(';', $headerMime)[0]));

        // vnd.microsoft.icon is an alias for x-icon.
        if ($mime === 'image/vnd.microsoft.icon') {
            $mime = 'image/x-icon';
        }

        return match ($mime) {
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'image/avif', 'image/svg+xml', 'image/x-icon' => $mime,
            default => null,
        };
    }
}
