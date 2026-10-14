<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\Preference;
use App\Salary\SalaryParseResult;

/**
 * Spec section 6 "Salary" pass/fail/ranking rules, applied to an
 * already-parsed SalaryParseResult (see SalaryParser).
 */
final class SalaryFilter
{
    public function check(SalaryParseResult $result, string $remoteType, Preference $preferences): FilterResult
    {
        $flags = array_filter([
            'tiered' => $result->flags['tiered'],
            'ote' => $result->flags['ote'],
            'commission' => $result->flags['commission'],
            'equity' => $result->flags['equity'],
            'non_usd' => $result->flags['non_usd'],
        ]);

        $data = [
            'tiers' => $result->tiers,
            'snippet' => $result->snippet,
            'confidence' => $result->confidence,
        ];

        if (! $result->hasParseableSalary()) {
            return FilterResult::unknown(['salary_unknown' => true], $data);
        }

        $top = $result->highestTop();

        if ($top === null) {
            // Only OTE/non-USD figures were found — no usable base figure.
            return FilterResult::unknown([...$flags, 'salary_unknown' => true], $data);
        }

        if (! $result->flags['ote']) {
            $flags['bonus_unknown'] = true;
        }

        $isOnsiteOrHybrid = in_array($remoteType, ['onsite', 'hybrid'], true);
        $floor = $isOnsiteOrHybrid ? $preferences->onsite_floor : $preferences->hard_min;

        if ($top < $floor) {
            if ($result->flags['open_ended']) {
                // "up to X" / "X+" figures can pass, never fail, since
                // the real number might clear the floor.
                return FilterResult::unknown([...$flags, 'open_ended' => true], $data);
            }

            return FilterResult::fail(
                "Top of range ({$top}) is below the {$floor} floor",
                $flags,
                $data,
            );
        }

        return FilterResult::pass($flags, $data);
    }
}
