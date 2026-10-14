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
}
