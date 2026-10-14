<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'name', 'url', 'driver', 'config', 'recipe', 'enabled', 'last_run_at', 'last_status'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * Coarse intake categories (spec section 4). Browser is registered
     * in the schema/UI now but has no working driver until the box
     * exists (spec's top-of-file note).
     */
    public const DRIVERS = ['email', 'rss', 'api', 'html', 'browser'];

    public const STATUSES = ['ok', 'needs_login', 'recipe_broken', 'error'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'recipe' => 'array',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
