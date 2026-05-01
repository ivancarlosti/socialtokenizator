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

    public static function cached()
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
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
        Cache::forget(self::CACHE_KEY);
    }

    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    public static function publicUrl(?string $r2Key): ?string
    {
        if (! $r2Key) return null;
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');
        return $base.'/'.ltrim($r2Key, '/');
    }
}
