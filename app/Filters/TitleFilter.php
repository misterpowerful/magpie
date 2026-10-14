<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\Preference;

/**
 * Spec section 6 stub filter: title include/exclude regex lists.
 * Exclude is a confident reject; include is only a soft positive signal
 * (matching one doesn't force a PASS elsewhere, but for this filter
 * specifically it means the title dimension is satisfied). Neither list
 * set, or an include list present with no match, is UNKNOWN — never an
 * outright accept or reject on title alone.
 */
final class TitleFilter
{
    public function check(string $title, Preference $preferences): FilterResult
    {
        foreach ($preferences->exclude_titles ?? [] as $pattern) {
            if ($this->matches($pattern, $title)) {
                return FilterResult::fail("Title matches exclude pattern: {$pattern}");
            }
        }

        $includePatterns = $preferences->include_titles ?? [];

        if ($includePatterns === []) {
            return FilterResult::unknown();
        }

        foreach ($includePatterns as $pattern) {
            if ($this->matches($pattern, $title)) {
                return FilterResult::pass();
            }
        }

        return FilterResult::unknown();
    }

    private function matches(string $pattern, string $title): bool
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/i';

        return @preg_match($delimited, $title) === 1;
    }
}
