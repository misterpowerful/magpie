<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Stats;

use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Listings pulled in per source (site, or "Email" for a null site_id —
 * see ListingSource) in the given range. Dashboard "listings by source" panel.
 */
final class SourceStatsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $since = StatsRange::since($request);

        $rows = DB::table('listing_sources')
            ->join('listings', 'listings.id', '=', 'listing_sources.listing_id')
            ->leftJoin('sites', 'sites.id', '=', 'listing_sources.site_id')
            ->where('listings.user_id', $request->user()->id)
            ->where('listing_sources.first_seen_at', '>=', $since)
            ->selectRaw("COALESCE(sites.name, 'Email') as source, COUNT(*) as count")
            ->groupBy('source')
            ->orderByDesc('count')
            ->get();

        return response()->json(['data' => $rows]);
    }
}
