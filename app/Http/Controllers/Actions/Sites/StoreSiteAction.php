<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Http\Requests\StoreSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;

final class StoreSiteAction
{
    public function __invoke(StoreSiteRequest $request): SiteResource
    {
        $site = Site::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return new SiteResource($site);
    }
}
