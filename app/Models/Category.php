<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['handle', 'name_en_US', 'name_es_MX', 'name_pt_BR'];

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class);
    }

    /**
     * Get the display name for the given locale, falling back through available languages.
     */
    public function getName(string $locale): ?string
    {
        $fallbackChain = [$locale];

        // Add fallback: pt_BR → es_MX → en_US
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
            $col = 'name_' . str_replace('-', '_', $loc);
            $val = $this->getAttribute($col);
            if (! empty($val)) {
                return $val;
            }
        }

        // Last resort: any non-null name
        foreach (['name_en_US', 'name_es_MX', 'name_pt_BR'] as $col) {
            $val = $this->getAttribute($col);
            if (! empty($val)) {
                return $val;
            }
        }

        return $this->handle;
    }
}
