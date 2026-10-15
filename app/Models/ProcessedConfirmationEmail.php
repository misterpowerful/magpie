<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dedup bookkeeping for the application-confirmation scan (spec section
 * 9's "deterministic confirmation matcher" alternative to Gatsby). Since
 * the owner asked that confirmation emails never be marked read/moved
 * (so he can still see them himself), there's no IMAP-side signal that
 * a message was already scanned — this table is that signal instead.
 */
#[Fillable(['message_id', 'outcome', 'listing_id', 'created_at'])]
class ProcessedConfirmationEmail extends Model
{
    public $timestamps = false;

    public const OUTCOMES = ['matched', 'ambiguous', 'no_match'];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
