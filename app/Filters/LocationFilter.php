<?php

namespace App\Filters;

/**
 * Spec section 6 "Work location". Unlike the other filters, ambiguity
 * here resolves to PASS-with-a-flag (the spec's own wording), not
 * UNKNOWN — a posting that doesn't clearly state eligibility is kept
 * in the running rather than stalled.
 */
class LocationFilter
{
    private const FULL_STATE_NAMES = 'Alabama|Alaska|Arizona|Arkansas|California|Colorado|Connecticut|Delaware|Florida|Georgia|Hawaii|Idaho|Illinois|Indiana|Iowa|Kansas|Kentucky|Louisiana|Maine|Maryland|Massachusetts|Michigan|Minnesota|Mississippi|Missouri|Montana|Nebraska|Nevada|New Hampshire|New Jersey|New Mexico|New York|North Carolina|North Dakota|Ohio|Oklahoma|Oregon|Pennsylvania|Rhode Island|South Carolina|South Dakota|Tennessee|Texas|Utah|Vermont|Virginia|Washington|West Virginia|Wisconsin|Wyoming';

    // Case-sensitive, uppercase-only abbreviations to avoid colliding with
    // common words ("in", "or", "me", "hi", "ok", "pa").
    private const STATE_ABBREVIATIONS = 'AL|AK|AZ|AR|CA|CO|CT|DE|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY';

    private const ELIGIBLE_STATES_ANCHOR = '/\b(eligible\s*states|open\s*to\s*candidates\s*in|authorized\s*to\s*work\s*in|we\s*can\s*hire\s*in|hiring\s*in|licensed\s*in|must\s*reside\s*in|must\s*be\s*located\s*in|candidates\s*must\s*be\s*based\s*in)\b/i';

    private const ONSITE_LOCATION_ANCHOR = '/\b(location|based\s*in|on-?site\s*in|office\s*in|headquartered\s*in|in-office)\b/i';

    private const CHICAGO_PATTERN = '/\bChicago\b/i';

    public function check(string $text, ?string $remoteType): FilterResult
    {
        return match ($remoteType) {
            'onsite', 'hybrid' => $this->checkOnsite($text),
            'remote' => $this->checkRemote($text),
            default => FilterResult::pass(['ambiguous_location' => true]),
        };
    }

    private function checkRemote(string $text): FilterResult
    {
        if (! preg_match(self::ELIGIBLE_STATES_ANCHOR, $text, $anchorMatch, PREG_OFFSET_CAPTURE)) {
            return FilterResult::pass();
        }

        $segment = $this->segmentAround($text, $anchorMatch[0][1], strlen($anchorMatch[0][0]));
        $states = $this->extractStates($segment);

        if ($states === []) {
            return FilterResult::pass(['ambiguous_location' => true]);
        }

        if (in_array('IL', $states, true)) {
            return FilterResult::pass();
        }

        return FilterResult::fail('Illinois is not in the posting\'s eligible-states list');
    }

    private function checkOnsite(string $text): FilterResult
    {
        $flags = preg_match(self::CHICAGO_PATTERN, $text) ? ['chicago' => true] : [];

        if (! preg_match(self::ONSITE_LOCATION_ANCHOR, $text, $anchorMatch, PREG_OFFSET_CAPTURE)) {
            return FilterResult::pass([...$flags, 'ambiguous_location' => true]);
        }

        $segment = $this->segmentAround($text, $anchorMatch[0][1], strlen($anchorMatch[0][0]));
        $states = $this->extractStates($segment);

        if ($states === []) {
            return FilterResult::pass([...$flags, 'ambiguous_location' => true]);
        }

        if (in_array('IL', $states, true) && count($states) === 1) {
            return FilterResult::pass($flags);
        }

        if (in_array('IL', $states, true)) {
            // Multiple states named alongside IL in the same clause is
            // ambiguous (e.g. a multi-office posting), not a confident fail.
            return FilterResult::pass([...$flags, 'ambiguous_location' => true]);
        }

        return FilterResult::fail('On-site/hybrid role is not based in Illinois');
    }

    /**
     * @return array<int, string> normalized two-letter state codes
     */
    private function extractStates(string $segment): array
    {
        $states = [];

        if (preg_match_all('/\b('.self::FULL_STATE_NAMES.')\b/i', $segment, $m)) {
            foreach ($m[1] as $name) {
                $states[] = $this->toAbbreviation($name);
            }
        }

        if (preg_match_all('/\b('.self::STATE_ABBREVIATIONS.')\b/', $segment, $m)) {
            foreach ($m[1] as $abbr) {
                $states[] = $abbr;
            }
        }

        return array_values(array_unique($states));
    }

    private function toAbbreviation(string $name): string
    {
        return match (strtolower($name)) {
            'illinois' => 'IL',
            'california' => 'CA',
            'new york' => 'NY',
            'colorado' => 'CO',
            'washington' => 'WA',
            'texas' => 'TX',
            default => strtoupper(substr($name, 0, 2)),
        };
    }

    private function segmentAround(string $text, int $offset, int $matchLength, int $radius = 120): string
    {
        $start = max(0, $offset - 10);
        $length = ($offset - $start) + $matchLength + $radius;

        return substr($text, $start, $length);
    }
}
