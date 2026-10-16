<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Runs;

use App\Http\Resources\RunResource;
use App\Models\Listing;
use App\Models\Run;
use App\Services\RunRequestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Spec section 10: "Dashboard shows backlog count ('unscored,
 * waiting'), cost/time per stage per run, and per-site status." This
 * covers the first two; per-site status is already on SiteResource.
 */
final class IndexRunsAction
{
    private const BACKLOG_STATES = ['new', 'fetched', 'filtered_in'];

    public function __construct(
        private readonly RunRequestService $requests,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $runs = Run::orderByDesc('started_at')->limit(10)->get();

        $backlog = Listing::where('user_id', $request->user()->id)
            ->whereIn('state', self::BACKLOG_STATES)
            ->count();

        return RunResource::collection($runs)->additional([
            'backlog' => $backlog,
            'pending' => $this->requests->isPending(),
            'running' => $this->requests->isRunning(),
        ]);
    }
}
