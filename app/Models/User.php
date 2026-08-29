<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Model
{
    protected $fillable = ['email', 'display_name', 'api_token'];

    /**
     * Resolve the name shown for this author.
     * Falls back to the email address when no display name is set.
     */
    public function displayName(): string
    {
        $name = trim((string) $this->display_name);
        return $name !== '' ? $name : (string) $this->email;
    }

    /**
     * Generate (or rotate) the user's API token and persist it.
     */
    public function generateApiToken(): string
    {
        $token = Str::random(64);
        $this->forceFill(['api_token' => $token])->save();

        return $token;
    }

    /**
     * Revoke the user's API token.
     */
    public function revokeApiToken(): void
    {
        $this->forceFill(['api_token' => null])->save();
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class, 'author_id');
    }
}
