<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateListingStatusRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $listings = Listing::where('user_id', $request->user()->id)
            ->orderByDesc('first_seen_at')
            ->paginate(25);

        return ListingResource::collection($listings);
    }

    public function updateStatus(UpdateListingStatusRequest $request, Listing $listing, ListingStatusService $service)
    {
        $service->setStatus(
            $listing,
            $request->validated('status'),
            'ui',
            $request->validated('reason'),
        );

        return new ListingResource($listing);
    }
}
