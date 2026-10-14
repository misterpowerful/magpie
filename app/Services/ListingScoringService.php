<?php

namespace App\Services;

use App\Contracts\LlmDriver;
use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\Preference;
use Illuminate\Support\Carbon;

/**
 * Spec section 7: survivors of the filter pipeline get scored by the
 * active LlmDriver. Applied/not-interested listings are never
 * rescored, and a listing is only rescored at all if the resume or
 * preferences changed since its last score.
 */
class ListingScoringService
{
    public function __construct(
        private readonly LlmDriver $driver,
    ) {}

    /**
     * @return ListingScore|null null when scoring was skipped (already
     *                           applied/not_interested, not a filter
     *                           survivor, or already up to date)
     */
    public function score(Listing $listing, Preference $preferences): ?ListingScore
    {
        if (in_array($listing->status, ['applied', 'not_interested'], true)) {
            return null;
        }

        if ($listing->state !== 'filtered_in') {
            return null;
        }

        $prefsHash = $preferences->scoringHash();
        $resumeHash = $preferences->resume_hash;

        // A fresh query, not the cached `score` relation — the caller
        // may be re-checking the same in-memory Listing instance after
        // a score was just persisted elsewhere.
        $existing = ListingScore::where('listing_id', $listing->id)->first();

        if ($existing && $existing->prefs_hash === $prefsHash && $existing->resume_hash === $resumeHash) {
            return null;
        }

        $result = $this->driver->score($listing, $preferences);

        $score = ListingScore::updateOrCreate(
            ['listing_id' => $listing->id],
            [
                'rating' => $result->rating,
                'reasons' => $result->reasons,
                'flags' => $result->flags,
                'commentary' => $result->commentary,
                'prefs_hash' => $prefsHash,
                'resume_hash' => $resumeHash,
                'model' => $result->model,
                'scored_at' => Carbon::now(),
            ],
        );

        $listing->update(['state' => 'scored']);

        return $score;
    }
}
