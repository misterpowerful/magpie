<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteIntakeService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request)
    {
        $sites = Site::where('user_id', $request->user()->id)->orderBy('name')->get();

        return SiteResource::collection($sites);
    }

    public function store(StoreSiteRequest $request)
    {
        $site = Site::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $site->update($request->validated());

        return new SiteResource($site);
    }

    public function destroy(Request $request, Site $site)
    {
        abort_unless($site->user_id === $request->user()->id, 403);

        $site->delete();

        return response()->noContent();
    }

    public function run(Request $request, Site $site, SiteIntakeService $service)
    {
        abort_unless($site->user_id === $request->user()->id, 403);

        $count = $service->run($site);

        return (new SiteResource($site->refresh()))->additional(['stubs_processed' => $count]);
    }
}
