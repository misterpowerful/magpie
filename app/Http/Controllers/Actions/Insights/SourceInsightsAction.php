<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Insights;

use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Insights → Sources tab: per source, how many listings it surfaced in
 * the range and how they rated — i.e. which sources are worth keeping.
 * A listing found on several sources counts once for each of them.
 */
final class SourceInsightsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = StatsRange::fromRequest($request);

        $rows = $range->apply(
            DB::table('listing_sources')
                ->join('listings', 'listings.id', '=', 'listing_sources.listing_id')
                ->leftJoin('sites', 'sites.id', '=', 'listing_sources.site_id')
                ->leftJoin('listing_scores', 'listing_scores.listing_id', '=', 'listings.id')
                ->where('listings.user_id', $request->user()->id),
            'listing_sources.first_seen_at',
        )
            ->selectRaw("COALESCE(sites.name, 'Email') as source")
            ->selectRaw("CASE WHEN sites.id IS NULL OR sites.driver = 'email' THEN 'email' WHEN sites.driver = 'browser' THEN 'browser' ELSE 'feeds' END as bucket")
            ->selectRaw("SUM(CASE WHEN listing_scores.rating = 'Strong' THEN 1 ELSE 0 END) as strong")
            ->selectRaw("SUM(CASE WHEN listing_scores.rating = 'Moderate' THEN 1 ELSE 0 END) as moderate")
            ->selectRaw("SUM(CASE WHEN listing_scores.rating = 'Weak' THEN 1 ELSE 0 END) as weak")
            ->selectRaw('SUM(CASE WHEN listing_scores.id IS NULL THEN 1 ELSE 0 END) as unscored')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('source', 'bucket')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'bucket' => $row->bucket,
                'Strong' => (int) $row->strong,
                'Moderate' => (int) $row->moderate,
                'Weak' => (int) $row->weak,
                'Unscored' => (int) $row->unscored,
                'total' => (int) $row->total,
            ]);

        return response()->json(['data' => $rows->values(), 'meta' => $range->meta()]);
    }
}
