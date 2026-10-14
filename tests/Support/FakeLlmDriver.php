<?php

namespace Tests\Support;

use App\Contracts\LlmDriver;
use App\Llm\ScoreResult;
use App\Models\Listing;
use App\Models\Preference;

class FakeLlmDriver implements LlmDriver
{
    public int $calls = 0;

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
}
