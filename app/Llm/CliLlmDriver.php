<?php

namespace App\Llm;

use App\Contracts\LlmDriver;
use App\Models\Listing;
use App\Models\Preference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Spec section 2/7: Claude Code headless on the owner's existing plan
 * (no API key, no per-token billing) — shells out to the `claude` CLI
 * in print mode. `--restricted` strips Bash/code-execution/WebFetch
 * tools (this is a pure text-judgment call, it needs none of them) and
 * `--permission-prompts none` guarantees it never hangs waiting for an
 * approval that can't come in an unattended run.
 */
class CliLlmDriver implements LlmDriver
{
    public function __construct(
        private readonly ScoringPromptBuilder $promptBuilder = new ScoringPromptBuilder,
    ) {}

    public function score(Listing $listing, Preference $preferences): ScoreResult
    {
        $prompt = $this->promptBuilder->build($listing, $preferences);
        $model = config('magpie.llm.scoring_model');

        $call = $this->invoke($prompt, $model, "listing:{$listing->id}");
        $parsed = $this->parseScoreJson($call['result']);

        return new ScoreResult(
            rating: $parsed['rating'],
            reasons: $parsed['reasons'],
            flags: $parsed['flags'],
            commentary: $parsed['commentary'],
            model: $model,
            durationMs: $call['duration_ms'],
            promptChars: strlen($prompt),
        );
    }

    public function complete(string $prompt, string $model): string
    {
        return $this->invoke($prompt, $model, 'generic')['result'];
    }

    /**
     * @return array{result: string, duration_ms: int, cost_usd: ?float}
     */
    private function invoke(string $prompt, string $model, string $context): array
    {
        $started = microtime(true);

        $result = Process::timeout(config('magpie.llm.cli_timeout'))->run([
            'claude',
            '-p', $prompt,
            '--output-format', 'json',
            '--model', $model,
            '--restricted',
            '--strict-mcp-config',
            '--disable-slash-commands',
            '--permission-prompts', 'none',
        ]);

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        if (! $result->successful()) {
            $this->log($context, $model, $durationMs, strlen($prompt), null, 'process_failed');

            throw new LlmDriverException("claude CLI exited {$result->exitCode()}: {$result->errorOutput()}");
        }

        $envelope = json_decode($result->output(), true);

        if (! is_array($envelope) || ($envelope['is_error'] ?? true)) {
            $this->log($context, $model, $durationMs, strlen($prompt), $envelope['total_cost_usd'] ?? null, 'cli_error');

            throw new LlmDriverException('claude CLI returned an error envelope: '.$result->output());
        }

        $this->log($context, $model, $durationMs, strlen($prompt), $envelope['total_cost_usd'] ?? null, 'ok');

        return [
            'result' => $envelope['result'] ?? '',
            'duration_ms' => $durationMs,
            'cost_usd' => $envelope['total_cost_usd'] ?? null,
        ];
    }

    /**
     * @return array{rating: string, reasons: array<int, string>, flags: array<int, string>, commentary: ?string}
     */
    private function parseScoreJson(string $text): array
    {
        $json = json_decode(trim($text), true);

        if (! is_array($json) || ! isset($json['rating']) || ! in_array($json['rating'], ScoreResult::RATINGS, true)) {
            throw new LlmDriverException("Model did not return a valid score JSON: {$text}");
        }

        $rating = $json['rating'];
        $commentary = $json['commentary'] ?? null;

        if ($rating !== 'Strong') {
            $commentary = null;
        }

        return [
            'rating' => $rating,
            'reasons' => array_values((array) ($json['reasons'] ?? [])),
            'flags' => array_values((array) ($json['flags'] ?? [])),
            'commentary' => $commentary,
        ];
    }

    private function log(string $context, string $model, int $durationMs, int $promptChars, ?float $costUsd, string $outcome): void
    {
        Log::channel('llm')->info('cli call', [
            'context' => $context,
            'model' => $model,
            'duration_ms' => $durationMs,
            'prompt_chars' => $promptChars,
            'cost_usd' => $costUsd,
            'outcome' => $outcome,
        ]);
    }
}
