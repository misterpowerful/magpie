<?php

declare(strict_types=1);

namespace App\Salary;

/**
 * Regex-first salary extraction (spec section 6 "Salary"). This is
 * deliberately not NLP-complete — it covers the named adversarial cases
 * (tiered ranges, "up to", OTE, hourly, non-salary dollar amounts,
 * non-USD). Anything it can't confidently resolve comes back with
 * confidence 'none' or 'low' and the caller treats that as UNKNOWN,
 * never FAIL. True ambiguity is meant for an LLM fallback in a later
 * ticket, not for ever-more regex here.
 */
final class SalaryParser
{
    private const ANCHOR_WORDS = '/\b(salary|base\s*salary|base\s*pay|base|compensation|comp|pay\s*range|pay|OTE|on[- ]target earnings|total\s*compensation)\b/i';

    private const PERIOD_ANNUAL = '/\b(per\s*year|\/\s*year|\/\s*yr|annually|per\s*annum)\b/i';

    private const PERIOD_HOURLY = '/\b(per\s*hour|\/\s*hour|\/\s*hr|hourly)\b/i';

    private const DISQUALIFYING_TERMS = '/\b(401\s?\(?k\)?\s*match|401\s?k\s*match|signing\s*bonus|sign-on\s*bonus|sign\s*on\s*bonus|relocation|stipend|referral\s*bonus|funding|raised|series\s*[a-e]\b|revenue|\bARR\b|valuation)\b/i';

    /**
     * Tight, directional OTE labeling — "OTE of $X" / "OTE: $X" right
     * before the figure, or "$X OTE" right after. Deliberately not a
     * wide proximity match, so a base figure earlier in the same
     * sentence as an OTE figure doesn't get mislabeled too.
     */
    private const OTE_PRECEDING = '/\b(OTE|on[- ]target earnings)\b\s*(of|is|:)?\s*$/i';

    private const OTE_FOLLOWING = '/^\s*(OTE)\b/i';

    private const COMMISSION_WORDS = '/\bcommission\b/i';

    private const EQUITY_WORDS = '/\bequity\b/i';

    private const NON_USD_SYMBOLS = '/[£€]/u';

    private const NON_USD_CODES = '/\b(GBP|EUR|CAD|AUD)\b/i';

    /**
     * A single dollar figure or range, e.g. "$140,000 - $160,000",
     * "$75k", "up to $180,000", "$150k+", "$42/hr". The currency symbol
     * also accepts £/€ purely so non-USD figures can be detected and
     * flagged — they're excluded from base tiers regardless.
     */
    private const FIGURE_PATTERN = '/
        (?<prefix>up\s*to\s*)?
        [\$£€]\s?(?<low>[\d,]+(?:\.\d+)?)\s?(?<lowk>[kK])?
        (?:
            \s*(?:-|–|—|to)\s*
            [\$£€]?\s?(?<high>[\d,]+(?:\.\d+)?)\s?(?<highk>[kK])?
        )?
        (?<plus>\s?\+)?
    /x';

    /**
     * US state names/abbreviations commonly used as tier labels in
     * "location-tiered" salary ranges.
     */
    private const TIER_LABEL_PATTERN = '/\b(Illinois|Chicago|IL|California|CA|New York|NY|Colorado|CO|Washington|WA|Rest of US|Rest of the US|National|Tier\s*\d+|Zone\s*[A-Z0-9]+)\b\s*[:\-]/i';

    public function parse(string $text): SalaryParseResult
    {
        $segments = $this->splitIntoSegments($text);

        $tiers = [];
        $snippet = null;
        $flags = [
            'ote' => false,
            'commission' => false,
            'equity' => false,
            'tiered' => false,
            'non_usd' => false,
            'open_ended' => false,
            'hourly' => false,
        ];

        foreach ($segments as $segment) {
            if (preg_match(self::COMMISSION_WORDS, $segment)) {
                $flags['commission'] = true;
            }
            if (preg_match(self::EQUITY_WORDS, $segment)) {
                $flags['equity'] = true;
            }

            if (! preg_match_all(self::FIGURE_PATTERN, $segment, $matches, PREG_SET_ORDER)) {
                continue;
            }

            $label = preg_match(self::TIER_LABEL_PATTERN, $segment, $labelMatch)
                ? $labelMatch[1]
                : null;

            foreach ($matches as $match) {
                $tier = $this->resolveFigure($match, $segment);

                if ($tier === null) {
                    continue;
                }

                $tier['label'] = $label;
                $tiers[] = $tier;
                $snippet ??= trim($segment);

                if ($tier['flags']['ote']) {
                    $flags['ote'] = true;
                }
                if ($tier['flags']['non_usd']) {
                    $flags['non_usd'] = true;
                }
                if ($tier['flags']['open_ended']) {
                    $flags['open_ended'] = true;
                }
                if ($tier['period'] === 'hour') {
                    $flags['hourly'] = true;
                }
            }
        }

        $baseTiers = array_values(array_filter($tiers, fn ($t) => $t['type'] === 'base'));

        if (count(array_unique(array_filter(array_column($baseTiers, 'label')))) > 1) {
            $flags['tiered'] = true;
        }

        $confidence = match (true) {
            count($baseTiers) > 0 => 'high',
            count($tiers) > 0 => 'low',
            default => 'none',
        };

        return new SalaryParseResult(
            tiers: array_map(fn ($t) => array_diff_key($t, ['flags' => null]), $tiers),
            snippet: $snippet,
            confidence: $confidence,
            flags: $flags,
        );
    }

    /**
     * @return array<string>
     */
    private function splitIntoSegments(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $segments = [];

        foreach ($lines as $line) {
            foreach (preg_split('/(?<=[.;])\s+/', $line) as $sentence) {
                if (trim($sentence) !== '') {
                    $segments[] = $sentence;
                }
            }
        }

        return $segments;
    }

    /**
     * @param  array<int|string, string>  $match
     * @return array{low: ?float, high: ?float, period: string, type: string, label: ?string, flags: array{ote: bool, non_usd: bool, open_ended: bool}}|null
     */
    private function resolveFigure(array $match, string $segment): ?array
    {
        $context = $this->context($segment, $match[0], before: 20, after: 25);

        if (preg_match(self::DISQUALIFYING_TERMS, $context) && ! preg_match(self::ANCHOR_WORDS, $context)) {
            return null;
        }

        $hasAnchor = preg_match(self::ANCHOR_WORDS, $context) === 1;
        $isHourly = preg_match(self::PERIOD_HOURLY, $context) === 1;
        $isAnnualMarker = preg_match(self::PERIOD_ANNUAL, $context) === 1;

        if (! $hasAnchor && ! $isHourly && ! $isAnnualMarker) {
            return null;
        }

        $nonUsd = preg_match(self::NON_USD_SYMBOLS, $context) === 1
            || preg_match(self::NON_USD_CODES, $context) === 1;

        $low = $this->numericValue($match['low'] ?? null, $match['lowk'] ?? '');
        $high = isset($match['high']) && $match['high'] !== ''
            ? $this->numericValue($match['high'], $match['highk'] ?? '')
            : null;

        $openEnded = false;
        $prefix = strtolower(trim($match['prefix'] ?? ''));
        $plus = trim($match['plus'] ?? '') === '+';

        if ($prefix === 'up to') {
            $high = $low;
            $low = null;
            $openEnded = true;
        } elseif ($plus) {
            $openEnded = true;
            // $low already set as the floor, $high stays null.
        }

        $period = $isHourly ? 'hour' : 'year';

        if ($period === 'hour') {
            $low = $low !== null ? $low * 2080 : null;
            $high = $high !== null ? $high * 2080 : null;
        }

        $isOte = $this->isOteFigure($segment, $match[0]);

        $type = match (true) {
            $nonUsd => 'non_usd',
            $isOte => 'ote',
            default => 'base',
        };

        return [
            'low' => $low,
            'high' => $high,
            'period' => $period,
            'type' => $type,
            'label' => null,
            'flags' => [
                'ote' => $isOte,
                'non_usd' => $nonUsd,
                'open_ended' => $openEnded,
            ],
        ];
    }

    /**
     * Tight, directional OTE check — see OTE_PRECEDING/OTE_FOLLOWING.
     */
    private function isOteFigure(string $segment, string $figureText): bool
    {
        $pos = strpos($segment, $figureText);

        if ($pos === false) {
            return false;
        }

        $before = substr($segment, max(0, $pos - 20), min(20, $pos));
        $after = substr($segment, $pos + strlen($figureText), 10);

        return preg_match(self::OTE_PRECEDING, $before) === 1
            || preg_match(self::OTE_FOLLOWING, $after) === 1;
    }

    private function numericValue(?string $raw, string $kSuffix): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (float) str_replace(',', '', $raw);

        if (strtolower($kSuffix) === 'k') {
            $value *= 1000;
        }

        return $value;
    }

    private function context(string $segment, string $figureText, int $before, int $after): string
    {
        $pos = strpos($segment, $figureText);

        if ($pos === false) {
            return $segment;
        }

        $start = max(0, $pos - $before);
        $length = ($pos - $start) + strlen($figureText) + $after;

        return substr($segment, $start, $length);
    }
}
