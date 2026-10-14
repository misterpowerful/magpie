<?php

namespace App\Contracts;

use App\Llm\ScoreResult;
use App\Models\Listing;
use App\Models\Preference;

/**
 * Spec section 2: `cli` driver (Claude Code headless on his plan) now,
 * `api` driver later by config — both implement this same contract so
 * the scoring stage doesn't care which is active.
 */
interface LlmDriver
{
    public function score(Listing $listing, Preference $preferences): ScoreResult;

    /**
     * A narrower, generic completion call for anything that isn't the
     * full scoring prompt — e.g. the Haiku fallback for unknown email
     * formats (spec section 5) or an ambiguous-salary paragraph (spec
     * section 6). The caller owns the prompt and the response schema;
     * this just returns the raw model output text.
     */
    public function complete(string $prompt, string $model): string;
}
