<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use App\Support\HtmlTextExtractor;
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
    /**
     * Confident phrases a job posting page uses when it's gating the
     * real content behind a sign-in — distinct from an empty JS shell,
     * where there's barely any text to search at all.
     */
    private const LOGIN_WALL_PATTERNS = [
        '/sign[\s-]*in\s+to\s+(view|continue|apply|see)/i',
        '/log[\s-]*in\s+to\s+(view|continue|apply|see)/i',
        '/create\s+a\s+(free\s+)?account\s+to\s+(view|continue|apply|see)/i',
        '/you\s+must\s+be\s+(signed|logged)\s+in/i',
        '/please\s+(sign|log)\s+in\s+to/i',
    ];

    public function __construct(private readonly HtmlTextExtractor $extractor) {}

    public function enrich(Listing $listing): string
    {
        try {
            $response = Http::timeout((int) config('magpie.enrichment.timeout'))->get($listing->canonical_url);
        } catch (ConnectionException $e) {
            return $this->markFailed($listing, "Connection error: {$e->getMessage()}");
        }

        if (in_array($response->status(), [401, 403], true)) {
            return $this->markNeedsBrowser($listing, "HTTP {$response->status()} — login required");
        }

        if ($response->failed()) {
            return $this->markFailed($listing, "HTTP {$response->status()}");
        }

        $text = $this->extractor->extractText($response->body());

        if ($this->looksLikeLoginWall($text)) {
            return $this->markNeedsBrowser($listing, 'Page content indicates a sign-in wall');
        }

        if (mb_strlen($text) < (int) config('magpie.enrichment.min_body_length')) {
            return $this->markNeedsBrowser($listing, 'Page body too short after stripping markup (likely a JS-rendered shell)');
        }

        $listing->update(['raw_text' => $text, 'state' => 'fetched']);

        return 'fetched';
    }

    private function looksLikeLoginWall(string $text): bool
    {
        foreach (self::LOGIN_WALL_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
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
