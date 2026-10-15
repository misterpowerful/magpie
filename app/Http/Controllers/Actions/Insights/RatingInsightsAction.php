<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Insights;

use App\Models\Listing;
use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Insights → Ratings tab: the Strong/Moderate/Weak/Unscored mix of the
 * listings each run in the range pulled in (first seen during that
 * run's window), so the trend reads run by run.
 */
final class RatingInsightsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = StatsRange::fromRequest($request);
        $userId = $request->user()->id;

        $runs = $range->runs()->map(function ($run) use ($userId) {
            $counts = Listing::where('listings.user_id', $userId)
                ->whereBetween('listings.first_seen_at', [$run->started_at, $run->ended_at ?? Carbon::now()])
                ->leftJoin('listing_scores', 'listing_scores.listing_id', '=', 'listings.id')
                ->selectRaw("COALESCE(listing_scores.rating, 'Unscored') as rating, COUNT(*) as count")
                ->groupBy('rating')
                ->pluck('count', 'rating');

            return [
                'run_id' => $run->id,
                'started_at' => $run->started_at->toIso8601String(),
                'Strong' => (int) ($counts['Strong'] ?? 0),
                'Moderate' => (int) ($counts['Moderate'] ?? 0),
                'Weak' => (int) ($counts['Weak'] ?? 0),
                'Unscored' => (int) ($counts['Unscored'] ?? 0),
            ];
        });

        return response()->json(['data' => ['runs' => $runs->values()], 'meta' => $range->meta()]);
    }
}
