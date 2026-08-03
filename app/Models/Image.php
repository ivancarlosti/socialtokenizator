<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Image extends Model
{
    protected $fillable = [
        'uuid',
        'r2_key',
        'original_filename',
        'mime_type',
        'width',
        'height',
        'headline',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if (empty($image->uuid)) {
                $image->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class)->orderBy('position');
    }

    public function getPublicUrlAttribute(): string
    {
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');
        return $base.'/'.ltrim($this->r2_key, '/');
    }
}
