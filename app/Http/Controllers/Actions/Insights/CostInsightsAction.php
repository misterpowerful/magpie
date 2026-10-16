<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Insights;

use App\Models\LlmCall;
use App\Models\Run;
use App\Support\StatsRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Insights → Cost tab: estimated LLM spend (API-equivalent, see LlmCall)
 * for the range — totals, cost per run over time, and where it went by
 * task and by model.
 *
 * Which calls count is StatsRange::llmCalls; in the day ranges that
 * includes calls made outside a run, which the per-run series can't show.
 */
final class CostInsightsAction
{
    /**
     * Call contexts are "listing:{id}" for scoring and "generic" for
     * complete(), whose only caller is the email parser's LLM fallback.
     */
    private const TASK_CASE = "CASE WHEN context LIKE 'listing:%' THEN 'Scoring' WHEN context = 'generic' THEN 'Email parsing' ELSE context END";

    public function __invoke(Request $request): JsonResponse
    {
        $range = StatsRange::fromRequest($request);

        // Aggregates, not columns: read them as attributes.
        $totals = $range->llmCalls()
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw("SUM(CASE WHEN outcome <> 'ok' THEN 1 ELSE 0 END) as failed_calls")
            ->selectRaw('SUM(cost_usd) as cost_usd')
            ->selectRaw('SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens')
            ->selectRaw('SUM(cache_read_input_tokens) as cache_read_input_tokens, SUM(cache_creation_input_tokens) as cache_creation_input_tokens')
            ->first();

        $runs = $range->runs();

        $perRun = LlmCall::whereIn('run_id', $runs->pluck('id'))
            ->groupBy('run_id')
            ->selectRaw('run_id, COUNT(*) as calls, SUM(cost_usd) as cost_usd')
            ->get()
            ->keyBy('run_id');

        return response()->json([
            'data' => [
                'totals' => [
                    'calls' => (int) $totals->getAttribute('calls'),
                    'failed_calls' => (int) $totals->getAttribute('failed_calls'),
                    'cost_usd' => (float) ($totals->getAttribute('cost_usd') ?? 0),
                    'input_tokens' => (int) ($totals->getAttribute('input_tokens') ?? 0),
                    'output_tokens' => (int) ($totals->getAttribute('output_tokens') ?? 0),
                    'cache_read_input_tokens' => (int) ($totals->getAttribute('cache_read_input_tokens') ?? 0),
                    'cache_creation_input_tokens' => (int) ($totals->getAttribute('cache_creation_input_tokens') ?? 0),
                ],
                'runs' => $runs->map(fn (Run $run) => [
                    'run_id' => $run->id,
                    'started_at' => $run->started_at->toIso8601String(),
                    'trigger' => $run->trigger,
                    'status' => $run->status,
                    'calls' => (int) ($perRun->get($run->id)?->getAttribute('calls') ?? 0),
                    'cost_usd' => (float) ($perRun->get($run->id)?->getAttribute('cost_usd') ?? 0),
                ])->values(),
                'by_task' => $this->breakdown($range->llmCalls(), self::TASK_CASE),
                'by_model' => $this->breakdown($range->llmCalls(), 'model'),
            ],
            'meta' => $range->meta(),
        ]);
    }

    /**
     * @param  Builder<LlmCall>  $query
     * @return array<int, array{label: string, calls: int, cost_usd: float}>
     */
    private function breakdown($query, string $expression): array
    {
        return $query
            ->selectRaw("{$expression} as label, COUNT(*) as calls, SUM(cost_usd) as cost_usd")
            ->groupByRaw($expression)
            ->orderByRaw('SUM(cost_usd) DESC')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->getAttribute('label'),
                'calls' => (int) $row->getAttribute('calls'),
                'cost_usd' => (float) ($row->getAttribute('cost_usd') ?? 0),
            ])
            ->all();
    }
}
