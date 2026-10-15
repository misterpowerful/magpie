<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Stats;

use App\Models\LlmCall;
use App\Support\StatsRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estimated LLM usage (calls, tokens, API-equivalent cost — the owner is
 * on Claude Pro via the CLI, not metered billing, so this is a cost
 * proxy, not a real charge) in the given range. Dashboard "estimated
 * cost" stat-tile panel.
 */
final class UsageStatsAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $since = StatsRange::since($request);

        $stats = LlmCall::where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as calls, SUM(cost_usd) as cost_usd, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->first();

        return response()->json(['data' => [
            'calls' => (int) $stats->calls,
            'cost_usd' => (float) ($stats->cost_usd ?? 0),
            'input_tokens' => (int) ($stats->input_tokens ?? 0),
            'output_tokens' => (int) ($stats->output_tokens ?? 0),
        ]]);
    }
}
