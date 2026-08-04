<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['handle', 'name_en', 'name_es', 'name_pt_BR'];

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

        // Add fallback: pt_BR → es → en
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
            $col = 'name_' . str_replace('-', '_', $loc);
            $val = $this->getAttribute($col);
            if (! empty($val)) {
                return $val;
            }
        }

        // Last resort: any non-null name
        foreach (['name_en', 'name_es', 'name_pt_BR'] as $col) {
            $val = $this->getAttribute($col);
            if (! empty($val)) {
                return $val;
            }
        }

        return $this->handle;
    }
}
