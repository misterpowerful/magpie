<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Listings;

use App\Http\Requests\UpdateListingStatusRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingStatusService;
use Illuminate\Contracts\Auth\Access\Gate;

final class UpdateListingStatusAction
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ListingStatusService $service,
    ) {}

    public function __invoke(UpdateListingStatusRequest $request, Listing $listing): ListingResource
    {
        $this->gate->authorize('update', $listing);

        $this->service->setStatus(
            $listing,
            $request->validated('status'),
            'ui',
            $request->validated('reason'),
        );

        return new ListingResource($listing);
    }
}
