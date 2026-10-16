<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ListingScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'rating', 'reasons', 'flags', 'commentary', 'prefs_hash', 'resume_hash', 'model', 'scored_at'])]
class ListingScore extends Model
{
    /** @use HasFactory<ListingScoreFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'flags' => 'array',
            'scored_at' => 'datetime',
        ];
    }
}
