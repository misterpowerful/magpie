<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Models\Site;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Response;

final class DestroySiteAction
{
    public function __construct(private readonly Gate $gate) {}

    public function __invoke(Site $site): Response
    {
        $this->gate->authorize('delete', $site);

        $site->delete();

        return response()->noContent();
    }
}
