<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Listings;

use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexListingsAction
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $listings = Listing::where('user_id', $request->user()->id)
            ->with(['score', 'salary', 'sources.site'])
            ->leftJoin('listing_scores', 'listing_scores.listing_id', '=', 'listings.id')
            ->select('listings.*')
            ->orderByRaw("CASE listing_scores.rating WHEN 'Strong' THEN 0 WHEN 'Moderate' THEN 1 WHEN 'Weak' THEN 2 ELSE 3 END")
            ->orderByDesc('listings.first_seen_at')
            ->paginate(25);

        return ListingResource::collection($listings);
    }
}
