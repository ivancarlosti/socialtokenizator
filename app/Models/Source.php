<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends Model
{
    protected $fillable = ['image_id', 'label', 'url', 'position'];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
