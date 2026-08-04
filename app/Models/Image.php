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
        'headline_en',
        'headline_es',
        'headline_pt_BR',
        'description_en',
        'description_es',
        'description_pt_BR',
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class)->orderBy('position');
    }

    /**
     * Get the headline for the given locale with fallback.
     */
    public function getHeadline(string $locale): ?string
    {
        return $this->getLocalizedField('headline', $locale);
    }

    /**
     * Get the description for the given locale with fallback.
     */
    public function getDescription(string $locale): ?string
    {
        return $this->getLocalizedField('description', $locale);
    }

    /**
     * Get a localized field using a fallback chain.
     */
    protected function getLocalizedField(string $field, string $locale): ?string
    {
        $fallbackChain = [$locale];

        if ($locale === 'pt_BR') {
            $fallbackChain[] = 'es';
            $fallbackChain[] = 'en';
        } elseif ($locale === 'es') {
            $fallbackChain[] = 'en';
            $fallbackChain[] = 'pt_BR';
        } elseif ($locale === 'en') {
            $fallbackChain[] = 'es';
            $fallbackChain[] = 'pt_BR';
        }

        foreach ($fallbackChain as $loc) {
            $col = $field . '_' . str_replace('-', '_', $loc);
            $val = $this->getAttribute($col);
            if (! empty($val)) {
                return $val;
            }
        }

        return null;
    }

    public function getPublicUrlAttribute(): string
    {
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');
        return $base.'/'.ltrim($this->r2_key, '/');
    }
}
