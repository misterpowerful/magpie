<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The dashboard's source tiles: every listing source rolls up into
 * "email" (no site, or an email-driver site), "browser" (the Claude in
 * Chrome tier), or "feeds" (rss/api/html). One definition, shared by
 * the dashboard stats and Insights → Sources so they can't disagree.
 */
final class SourceBuckets
{
    public const BUCKETS = ['email', 'feeds', 'browser'];

    private const BUCKET_SQL = "CASE WHEN sites.id IS NULL OR sites.driver = 'email' THEN 'email' "
        ."WHEN sites.driver = 'browser' THEN 'browser' ELSE 'feeds' END";

    /**
     * A user's listing_sources rows within the range, joined to their
     * listing and (optional) site, selecting `source` (site name, or
     * "Email") and `bucket`. Callers add their own aggregates and
     * group by both.
     */
    public static function query(int $userId, StatsRange $range): Builder
    {
        return $range->apply(
            DB::table('listing_sources')
                ->join('listings', 'listings.id', '=', 'listing_sources.listing_id')
                ->leftJoin('sites', 'sites.id', '=', 'listing_sources.site_id')
                ->where('listings.user_id', $userId),
            'listing_sources.first_seen_at',
        )
            ->selectRaw("COALESCE(sites.name, 'Email') as source")
            ->selectRaw(self::BUCKET_SQL.' as bucket')
            ->groupBy('source', 'bucket');
    }
}
