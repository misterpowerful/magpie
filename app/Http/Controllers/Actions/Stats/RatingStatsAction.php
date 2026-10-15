<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Stats;

use App\Models\Listing;
use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Strong/Moderate/Weak/Unscored breakdown of listings first seen in the
 * given range. Dashboard "rating distribution" pie panel.
 */
final class RatingStatsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $since = StatsRange::since($request);
        $userId = $request->user()->id;

        $rated = DB::table('listings')
            ->join('listing_scores', 'listing_scores.listing_id', '=', 'listings.id')
            ->where('listings.user_id', $userId)
            ->where('listings.first_seen_at', '>=', $since)
            ->select('listing_scores.rating', DB::raw('count(*) as count'))
            ->groupBy('listing_scores.rating')
            ->pluck('count', 'rating');

        $unscored = Listing::where('user_id', $userId)
            ->where('first_seen_at', '>=', $since)
            ->whereDoesntHave('score')
            ->count();

        return response()->json(['data' => [
            'Strong' => (int) ($rated['Strong'] ?? 0),
            'Moderate' => (int) ($rated['Moderate'] ?? 0),
            'Weak' => (int) ($rated['Weak'] ?? 0),
            'Unscored' => $unscored,
        ]]);
    }
}
