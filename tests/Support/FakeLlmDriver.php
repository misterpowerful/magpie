<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\LlmDriver;
use App\Llm\ScoreResult;
use App\Models\Listing;
use App\Models\Preference;

class FakeLlmDriver implements LlmDriver
{
    public int $calls = 0;

    public int $completeCalls = 0;

    public array $completePrompts = [];

    public string $completeResponse = '[]';

    public function __construct(
        private readonly string $rating = 'Strong',
    ) {}

    public function score(Listing $listing, Preference $preferences): ScoreResult
    {
        $this->calls++;

        return new ScoreResult(
            rating: $this->rating,
            reasons: ['Good stack fit'],
            flags: [],
            commentary: $this->rating === 'Strong' ? 'Great match overall.' : null,
            model: 'fake-model',
            durationMs: 1,
            promptChars: 100,
        );
    }

    public function complete(string $prompt, string $model): string
    {
        $this->completeCalls++;
        $this->completePrompts[] = $prompt;

        return $this->completeResponse;
    }
}
