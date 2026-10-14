<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Contracts\Auth\Access\Gate;

final class UpdateSiteAction
{
    public function __construct(private readonly Gate $gate) {}

    public function __invoke(UpdateSiteRequest $request, Site $site): SiteResource
    {
        $this->gate->authorize('update', $site);

        $site->update($request->validated());

        return new SiteResource($site);
    }
}
