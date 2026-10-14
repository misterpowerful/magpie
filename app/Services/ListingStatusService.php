<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Carbon;

final class ListingStatusService
{
    /**
     * Set a listing's triage status and append a history row. Shared by
     * the dashboard (source "ui") and, later, the Gatsby API (source
     * "gatsby") per spec section 9 — every write must be logged so a
     * mistake is visible and reversible.
     */
    public function setStatus(Listing $listing, string $status, string $source, ?string $reason = null): Listing
    {
        $appliedAt = $status === 'applied' ? Carbon::now() : null;

        $listing->update([
            'status' => $status,
            'status_reason' => $reason,
            'applied_at' => $appliedAt,
        ]);

        $listing->statusHistory()->create([
            'status' => $status,
            'source' => $source,
            'reason' => $reason,
            'applied_at' => $appliedAt,
        ]);

        return $listing;
    }
}
