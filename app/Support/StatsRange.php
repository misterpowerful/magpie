<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared `?range=1d|7d` parsing for the dashboard stats panels — each
 * panel toggles its own range independently (spec: per-panel, not a
 * single shared dashboard-wide filter).
 */
final class StatsRange
{
    public static function since(Request $request): Carbon
    {
        $days = $request->query('range') === '7d' ? 7 : 1;

        return Carbon::now()->subDays($days);
    }
}
