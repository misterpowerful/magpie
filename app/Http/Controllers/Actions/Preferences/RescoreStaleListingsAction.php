<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Preferences;

use App\Models\Preference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Queues pending listings scored against old criteria or an old resume
 * for re-scoring, by moving them back to filtered_in — the next run's
 * scoring stage picks them up like any unscored survivor (and skips any
 * that turn out to be current after all). Their existing score stays
 * visible until the new one replaces it. Applied / not-interested
 * listings are never touched.
 */
final class RescoreStaleListingsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $preferences = Preference::firstOrCreate(['user_id' => $request->user()->id]);

        $queued = $preferences->staleScoredListings()->update(['state' => 'filtered_in']);

        return response()->json(['data' => ['queued' => $queued]]);
    }
}
