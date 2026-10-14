<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class IndexSitesAction
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $sites = Site::where('user_id', $request->user()->id)->orderBy('name')->get();

        return SiteResource::collection($sites);
    }
}
