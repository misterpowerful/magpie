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
            ->orderByDesc('first_seen_at')
            ->paginate(25);

        return ListingResource::collection($listings);
    }
}
