<?php

declare(strict_types=1);

namespace App\Llm;

use App\Models\Listing;
use App\Models\Preference;
use App\Support\ListingTextTrimmer;

/**
 * Spec section 7: "Prompt order for caching: resume and preferences
 * first, posting last." The static prefix (instructions + resume +
 * preferences) is byte-identical across every listing scored in the
 * same run, so it's built once per run and reused — only the posting
 * section changes per call.
 */
final class ScoringPromptBuilder
{
    public function __construct(
        private readonly ListingTextTrimmer $trimmer = new ListingTextTrimmer,
    ) {}

    public function staticPrefix(Preference $preferences): string
    {
        $instructions = <<<'TEXT'
            You are screening a job posting against a candidate's resume and
            preferences. Respond with ONLY a single JSON object, no prose
            outside it, matching exactly this shape:

            {"rating": "Strong"|"Moderate"|"Weak", "reasons": ["short reason", ...], "flags": ["short flag", ...], "commentary": "1-2 sentences, or null"}

            Judge on: stack fit, remote/location fit, compensation vs. the
            stated floor, consumer-facing impact, mission/stability
            alignment, and honest naming of any gaps between the resume and
            the role. "commentary" is required for a "Strong" rating and
            must be null for "Moderate" or "Weak".
            TEXT;

        $resume = "## Candidate resume\n\n".($preferences->resume_text ?? '(no resume text on file)');

        $prefs = "## Preferences\n\n".json_encode([
            'remote_floor' => $preferences->remote_floor,
            'onsite_floor' => $preferences->onsite_floor,
            'hard_min' => $preferences->hard_min,
            'onsite_states' => $preferences->onsite_states,
            'include_stack' => $preferences->include_stack,
            'exclude_stack' => $preferences->exclude_stack,
        ], JSON_PRETTY_PRINT);

        return "{$instructions}\n\n{$resume}\n\n{$prefs}";
    }

    public function postingSection(Listing $listing): string
    {
        $trimmed = $this->trimmer->trim($listing->raw_text ?? '');

        return "## Posting\n\n".
            "Company: {$listing->company}\n".
            "Title: {$listing->title}\n".
            "Remote type: {$listing->remote_type}\n\n".
            $trimmed;
    }

    public function build(Listing $listing, Preference $preferences): string
    {
        return $this->staticPrefix($preferences)."\n\n".$this->postingSection($listing);
    }
}
