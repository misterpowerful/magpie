<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'canonical_url',
    'company',
    'title',
    'location_text',
    'remote_type',
    'raw_text',
    'content_hash',
    'state',
    'status',
    'status_reason',
    'applied_at',
    'first_seen_at',
    'filter_reason',
    'filter_flags',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * Pipeline processing states (spec section 4 "state").
     */
    public const STATES = ['new', 'fetched', 'filtered_out', 'filtered_in', 'scored', 'enrichment_failed', 'needs_browser'];

    /**
     * User-facing triage statuses (spec section 4 "listing_status").
     */
    public const STATUSES = ['new', 'applied', 'not_interested'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ListingStatusHistory::class)->latest('created_at');
    }

    public function salary(): HasOne
    {
        return $this->hasOne(ListingSalary::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(ListingScore::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'applied_at' => 'datetime',
            'filter_flags' => 'array',
        ];
    }
}
