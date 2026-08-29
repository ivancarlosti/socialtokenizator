<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = ['email', 'display_name'];

    /**
     * Resolve the name shown for this author.
     * Falls back to the email address when no display name is set.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->display_name);
        return $name !== '' ? $name : (string) $this->email;
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'author_id');
    }
}
