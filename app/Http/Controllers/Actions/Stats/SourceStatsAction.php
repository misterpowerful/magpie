<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Stats;

use App\Support\SourceBuckets;
use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listings pulled in per source (site, or "Email" for a null site_id —
 * see ListingSource) in the given range. Dashboard source panels.
 *
 * Each row also carries the dashboard tile it rolls up into (see
 * SourceBuckets). `buckets` has the per-tile totals, always with all
 * three keys so a tile with nothing in range still reads 0.
 */
final class SourceStatsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $range = StatsRange::fromRequest($request);

        $rows = SourceBuckets::query($request->user()->id, $range)
            ->selectRaw('COUNT(*) as count')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['source' => $row->source, 'bucket' => $row->bucket, 'count' => (int) $row->count]);

        $buckets = array_fill_keys(SourceBuckets::BUCKETS, 0);

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
