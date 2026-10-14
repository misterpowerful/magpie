<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Services\SiteIntakeService;
use Illuminate\Contracts\Auth\Access\Gate;

final class RunSiteAction
{
    public function __construct(
        private readonly Gate $gate,
        private readonly SiteIntakeService $service,
    ) {}

    public function __invoke(Site $site): SiteResource
    {
        $this->gate->authorize('run', $site);

        $count = $this->service->run($site);

        return (new SiteResource($site->refresh()))->additional(['stubs_processed' => $count]);
    }
}
