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
        'headline_en_US',
        'headline_es_MX',
        'headline_pt_BR',
        'description_en_US',
        'description_es_MX',
        'description_pt_BR',
    ];

    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if (empty($image->uuid)) {
                $image->uuid = (string) Str::uuid();
            }
        });

        // Invalidate web-standards caches whenever a post changes
        static::created(function (Image $image) {
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_full_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.sitemap_xml');
        });

        static::updated(function (Image $image) {
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_full_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.sitemap_xml');
        });

        static::deleted(function (Image $image) {
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.llms_full_txt');
            \Illuminate\Support\Facades\Cache::forget('web_standards.sitemap_xml');
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
            $fallbackChain[] = 'es_MX';
            $fallbackChain[] = 'en_US';
        } elseif ($locale === 'es_MX') {
            $fallbackChain[] = 'en_US';
            $fallbackChain[] = 'pt_BR';
        } elseif ($locale === 'en_US') {
            $fallbackChain[] = 'es_MX';
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
