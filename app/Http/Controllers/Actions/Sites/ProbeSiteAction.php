<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Sites;

use App\Http\Requests\ProbeSiteRequest;
use App\Services\SiteProbeService;
use Illuminate\Http\JsonResponse;

/**
 * Add Site form's "Check URL": which driver can read this URL from
 * this server (see SiteProbeService). Read-only — saves nothing.
 */
final class ProbeSiteAction
{
    public function __invoke(ProbeSiteRequest $request, SiteProbeService $probe): JsonResponse
    {
        return response()->json(['data' => $probe->probe($request->validated('url'))]);
    }
}
