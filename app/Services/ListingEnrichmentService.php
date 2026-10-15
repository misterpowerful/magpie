<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use App\Support\PageAccess;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Spec section 5 "Enrichment escalation (applies to every listing)":
 * a plain HTTP fetch of the posting. If the response is a login wall,
 * sign-in redirect, or empty JS shell, the listing moves to
 * needs_browser instead of fetched — it stays visible in the dashboard
 * and waits there until the browser tier (a later phase) exists.
 * A listing is only ever enriched once it has survived stub filters
 * and is still in the "new" state.
 */
final class ListingEnrichmentService
{
    public function __construct(private readonly PageAccess $pageAccess) {}

    public function enrich(Listing $listing): string
    {
        try {
            $response = Http::timeout((int) config('magpie.enrichment.timeout'))->get($listing->canonical_url);
        } catch (ConnectionException $e) {
            return $this->markFailed($listing, "Connection error: {$e->getMessage()}");
        }

        $access = $this->pageAccess->assess($response);

        if ($access['verdict'] === PageAccess::NEEDS_BROWSER) {
            return $this->markNeedsBrowser($listing, $access['reason']);
        }

        // A 404/5xx is the posting being gone or the site being down —
        // a failure, not a reason to escalate to the browser tier.
        if ($access['verdict'] === PageAccess::HTTP_ERROR) {
            return $this->markFailed($listing, $access['reason']);
        }

        $text = $access['text'];

        $listing->update(['raw_text' => $text, 'state' => 'fetched']);

        return 'fetched';
    }

    private function markNeedsBrowser(Listing $listing, string $reason): string
    {
        $listing->update(['state' => 'needs_browser', 'filter_reason' => $reason]);

        return 'needs_browser';
    }

    private function markFailed(Listing $listing, string $reason): string
    {
        $listing->update(['state' => 'enrichment_failed', 'filter_reason' => $reason]);

        return 'enrichment_failed';
    }
}
