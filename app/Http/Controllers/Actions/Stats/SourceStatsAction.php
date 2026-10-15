<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Stats;

use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Listings pulled in per source (site, or "Email" for a null site_id —
 * see ListingSource) in the given range. Dashboard source panels.
 *
 * Each row also carries the dashboard tile it rolls up into, derived
 * from sites.driver: "email" (no site, or an email-driver site),
 * "browser" (the Claude-in-Chrome driver), and "feeds" for everything
 * else (rss/api/html). `buckets` has the per-tile totals, always with
 * all three keys so a tile with nothing in range still reads 0.
 */
final class SourceStatsAction
{
    private const BUCKETS = ['email', 'feeds', 'browser'];

    public function __invoke(Request $request): JsonResponse
    {
        $range = StatsRange::fromRequest($request);

        $rows = $range->apply(
            DB::table('listing_sources')
                ->join('listings', 'listings.id', '=', 'listing_sources.listing_id')
                ->leftJoin('sites', 'sites.id', '=', 'listing_sources.site_id')
                ->where('listings.user_id', $request->user()->id),
            'listing_sources.first_seen_at',
        )
            ->selectRaw("COALESCE(sites.name, 'Email') as source")
            ->selectRaw("CASE WHEN sites.id IS NULL OR sites.driver = 'email' THEN 'email' WHEN sites.driver = 'browser' THEN 'browser' ELSE 'feeds' END as bucket")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('source', 'bucket')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['source' => $row->source, 'bucket' => $row->bucket, 'count' => (int) $row->count]);

        $buckets = array_fill_keys(self::BUCKETS, 0);

        foreach ($rows as $row) {
            $buckets[$row['bucket']] += $row['count'];
        }

        return response()->json([
            'data' => $rows->values(),
            'buckets' => $buckets,
            'meta' => $range->meta(),
        ]);
    }
}
