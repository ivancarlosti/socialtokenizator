<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class ShortId
{
    public const DEFAULT_LENGTH = 6;
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 32;

    /**
     * Resolve the configured short ID length, clamped to a safe range.
     */
    public static function length(): int
    {
        $length = (int) Setting::get('short_id_length', self::DEFAULT_LENGTH);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return self::DEFAULT_LENGTH;
        }

        return $length;
    }

    /**
     * Build the alphabet from the configured options.
     * Lowercase letters are always included.
     */
    public static function alphabet(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';

        if ((bool) Setting::get('short_id_uppercase')) {
            $alphabet .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if ((bool) Setting::get('short_id_numbers')) {
            $alphabet .= '0123456789';
        }

        return $alphabet;
    }

    /**
     * Generate a single random ID from the given alphabet and length.
     */
    public static function generate(?int $length = null, ?string $alphabet = null): string
    {
        $length ??= self::length();
        $alphabet ??= self::alphabet();

        $max = strlen($alphabet) - 1;
        $id = '';

        for ($i = 0; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, $max)];
        }

        return $id;
    }

    /**
     * Generate an ID that does not already exist in the images table.
     */
    public static function unique(?int $length = null, ?string $alphabet = null, int $attempts = 100): string
    {
        $exists = false;

        do {
            $id = self::generate($length, $alphabet);
            $exists = DB::table('images')->where('short_id', $id)->exists();
        } while ($exists && --$attempts > 0);

        if ($exists) {
            throw new \RuntimeException('Unable to generate a unique short post ID.');
        }

        return $id;
    }
}
