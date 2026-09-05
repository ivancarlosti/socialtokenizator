<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the 1200x630 OpenGraph/feed thumbnail for a post.
 *
 * The original image is scaled to fit inside a 1200x630 solid-black canvas
 * while preserving its aspect ratio, then encoded as JPEG (quality 85) and
 * uploaded back to R2 under the "og/{uuid}.jpg" key.
 */
final class OgImageProcessor
{
    public const WIDTH = 1200;
    public const HEIGHT = 630;
    public const QUALITY = 85;
    public const MIME = 'image/jpeg';

    /**
     * Generate the thumbnail for an image and return its R2 key.
     *
     * Returns null when generation is not possible (missing original,
     * unavailable GD decoder, or encoding failure) so callers can leave
     * images.og_image_key null and fall back to the original URL.
     */
    public static function generate(Image $image): ?string
    {
        if (! self::gdAvailable()) {
            Log::warning('OgImageProcessor: GD is not available; skipping thumbnail.', [
                'image_id' => $image->id,
            ]);

            return null;
        }

        $originalKey = $image->r2_key;
        if ($originalKey === null || $originalKey === '') {
            return null;
        }

        $bytes = Storage::disk('r2')->get($originalKey);
        if (! is_string($bytes) || $bytes === '') {
            Log::warning('OgImageProcessor: unable to read original from R2.', [
                'image_id' => $image->id,
                'r2_key'   => $originalKey,
            ]);

            return null;
        }

        $source = self::decode($bytes, $image->mime_type);
        if ($source === null) {
            Log::warning('OgImageProcessor: unable to decode original image.', [
                'image_id' => $image->id,
                'mime_type' => $image->mime_type,
            ]);

            return null;
        }

        try {
            $jpeg = self::letterbox($source);
        } finally {
            imagedestroy($source);
        }

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        $key = 'og/'.$image->uuid.'.jpg';
        Storage::disk('r2')->put($key, $jpeg, [
            'visibility'  => 'public',
            'ContentType' => self::MIME,
        ]);

        return $key;
    }

    private static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagejpeg');
    }

    /**
     * Decode raw image bytes into a GD image resource using the stored MIME.
     */
    private static function decode(string $bytes, ?string $mime)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'og_');
        if ($tmp === false) {
            return null;
        }

        if (@file_put_contents($tmp, $bytes) === false) {
            @unlink($tmp);

            return null;
        }

        $source = self::createImageFromFile($tmp, $mime);
        @unlink($tmp);

        return $source;
    }

    private static function createImageFromFile(string $path, ?string $mime)
    {
        return match (strtolower((string) $mime)) {
            'image/png'  => @imagecreatefrompng($path),
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : null,
            default      => null,
        };
    }

    /**
     * Scale-to-fit the source onto a 1200x630 black canvas and return JPEG bytes.
     */
    private static function letterbox($source): ?string
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        if ($srcWidth < 1 || $srcHeight < 1) {
            return null;
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($canvas === false) {
            return null;
        }

        imagealphablending($canvas, true);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $black);

        $scale = min(self::WIDTH / $srcWidth, self::HEIGHT / $srcHeight);
        $dstWidth = (int) round($srcWidth * $scale);
        $dstHeight = (int) round($srcHeight * $scale);
        $dstX = (int) floor((self::WIDTH - $dstWidth) / 2);
        $dstY = (int) floor((self::HEIGHT - $dstHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $dstX,
            $dstY,
            0,
            0,
            $dstWidth,
            $dstHeight,
            $srcWidth,
            $srcHeight
        );

        ob_start();
        $ok = imagejpeg($canvas, null, self::QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        if ($ok !== true || $jpeg === false || $jpeg === '') {
            return null;
        }

        return $jpeg;
    }
}
