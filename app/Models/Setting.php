<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'app.settings';

    /** Seconds before the settings cache automatically expires. */
    private const CACHE_TTL = 5;

    public static function cached()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::query()->pluck('value', 'key');
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::cached()->get($key);
        return $value !== null && $value !== '' ? $value : $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
        static::flushCache();
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
        static::flushCache();
    }

    /** Immediately invalidate the settings cache so the next read hits the database. */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function publicUrl(?string $r2Key): ?string
    {
        if (! $r2Key) return null;
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');
        return $base.'/'.ltrim($r2Key, '/');
    }
}
