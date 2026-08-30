<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates the favicon variants exposed in the site <head>.
 *
 * - SVG uploads are kept as-is (the original is the favicon.svg).
 * - Raster uploads (PNG/JPEG/WebP/GIF/AVIF) are additionally resized via GD
 *   to 32x32, 180x180 (apple-touch-icon), 192x192 and 512x512 PNGs.
 * - ICO uploads are kept as-is only (GD has no ICO decoder).
 */
final class FaviconProcessor
{
    private const PREFIX = 'branding/favicon';

    private const SIZES = [
        '32'  => [32, 'favicon-32x32.png'],
        '180' => [180, 'apple-touch-icon.png'],
        '192' => [192, 'android-chrome-192x192.png'],
        '512' => [512, 'android-chrome-512x512.png'],
    ];

    /**
     * Process an uploaded favicon and return the R2 keys of every generated
     * variant: original, svg, 32, 180, 192, 512 (nullable when not produced).
     *
     * @return array{original:string, svg:?string, 32:?string, 180:?string, 192:?string, 512:?string}
     */
    public static function process(UploadedFile $file): array
    {
        $mime = ImageMime::ofUploadedFile($file) ?? $file->getMimeType();
        $ext = ImageMime::extension($mime);

        $variants = [
            'original' => null,
            'svg'      => null,
            '32'       => null,
            '180'      => null,
            '192'      => null,
            '512'      => null,
        ];

        // SVG: the uploaded file itself is the scalable favicon.
        if ($mime === 'image/svg+xml') {
            $key = self::PREFIX.'/favicon.svg';
            Storage::disk('r2')->putFileAs('', $file, $key, [
                'visibility'  => 'public',
                'ContentType' => 'image/svg+xml',
            ]);

            return [
                'original' => $key,
                'svg'      => $key,
                '32'       => null,
                '180'      => null,
                '192'      => null,
                '512'      => null,
            ];
        }

        // Original (fallback icon) keeps the historical UUID key layout.
        $originalKey = self::PREFIX.'/'.Str::uuid().'.'.$ext;
        Storage::disk('r2')->putFileAs('', $file, $originalKey, [
            'visibility'  => 'public',
            'ContentType' => $mime,
        ]);
        $variants['original'] = $originalKey;

        // ICO is not decodable by GD — keep only the original.
        if ($mime === 'image/x-icon') {
            return $variants;
        }

        if (! self::gdAvailable()) {
            return $variants;
        }

        $source = self::createImageFromFile($file->getRealPath(), $mime);
        if ($source === null) {
            return $variants;
        }

        try {
            foreach (self::SIZES as $label => [$size, $filename]) {
                $key = self::PREFIX.'/'.$filename;
                if (self::storeResized($source, $size, $key)) {
                    $variants[$label] = $key;
                }
            }
        } finally {
            imagedestroy($source);
        }

        return $variants;
    }

    private static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagepng');
    }

    private static function createImageFromFile(string $path, string $mime)
    {
        return match ($mime) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/avif' => @imagecreatefromavif($path),
            default      => null,
        };
    }

    private static function storeResized($source, int $size, string $key): bool
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        if ($srcWidth < 1 || $srcHeight < 1) {
            return false;
        }

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);

        imagecopyresampled($dst, $source, 0, 0, 0, 0, $size, $size, $srcWidth, $srcHeight);

        ob_start();
        $ok = imagepng($dst, null, 9);
        $png = ob_get_clean();
        imagedestroy($dst);

        if ($ok !== true || $png === false || $png === '') {
            return false;
        }

        Storage::disk('r2')->put($key, $png, [
            'visibility'  => 'public',
            'ContentType' => 'image/png',
        ]);

        return true;
    }
}
