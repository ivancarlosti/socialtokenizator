<?php

namespace App\Rules;

use App\Support\ImageMime;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates an uploaded image by sniffing its actual content instead of
 * trusting the client-supplied MIME type or finfo/libmagic, which may not
 * recognize AVIF.
 */
class ImageFile implements ValidationRule
{
    /**
     * @param  array<int, string>  $allowedExtensions  e.g. ['jpeg','png','webp','gif','avif']
     */
    public function __construct(private readonly array $allowedExtensions = [])
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(__('validation.image'));

            return;
        }

        $mime = ImageMime::ofUploadedFile($value);

        if ($mime === null) {
            $fail(__('validation.image'));

            return;
        }

        if ($this->allowedExtensions === []) {
            return;
        }

        $ext = ImageMime::extension($mime);

        $allowed = array_map(function (string $e): string {
            $e = strtolower($e);

            // jpeg is stored with the jpg extension.
            return $e === 'jpeg' ? 'jpg' : $e;
        }, $this->allowedExtensions);

        if (! in_array($ext, $allowed, true)) {
            $fail(__('validation.image'));
        }
    }
}
