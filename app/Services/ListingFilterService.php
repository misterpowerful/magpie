<?php

namespace App\Services;

use App\Filters\FilterResult;
use App\Filters\LocationFilter;
use App\Filters\SalaryFilter;
use App\Filters\StackFilter;
use App\Filters\TitleFilter;
use App\Models\Listing;
use App\Models\ListingSalary;
use App\Models\Preference;
use App\Salary\SalaryParser;

/**
 * Runs the spec section 6 filter pipeline in two stages:
 *
 * - Stub filters (title, obvious location) run on a bare "new" listing
 *   stub, before any enrichment/fetch has happened.
 * - Hard filters (salary, location, stack) run once the full body text
 *   has been fetched (listing.state === 'fetched', raw_text present —
 *   enrichment itself is a later ticket's job).
 *
 * Only a confident FAIL moves a listing to filtered_out; everything
 * else proceeds (stub: stays 'new' awaiting enrichment; hard: becomes
 * filtered_in, ready for scoring in a later ticket).
 */
class ListingFilterService
{
    public function __construct(
        private readonly TitleFilter $titleFilter,
        private readonly LocationFilter $locationFilter,
        private readonly StackFilter $stackFilter,
        private readonly SalaryFilter $salaryFilter,
        private readonly SalaryParser $salaryParser,
    ) {}

    public function runStubFilters(Listing $listing, Preference $preferences): FilterResult
    {
        $title = $this->titleFilter->check($listing->title, $preferences);

        if ($title->isFail()) {
            return $this->reject($listing, $title);
        }

        $location = $this->locationFilter->check($listing->location_text ?? '', $listing->remote_type);

        if ($location->isFail()) {
            return $this->reject($listing, $location);
        }

        return FilterResult::unknown();
    }

    public function runHardFilters(Listing $listing, Preference $preferences): FilterResult
    {
        $text = $listing->raw_text ?? '';
        $remoteType = $listing->remote_type;

        $salaryParse = $this->salaryParser->parse($text);
        $salary = $this->salaryFilter->check($salaryParse, $remoteType ?? 'unknown', $preferences);
        $this->persistSalary($listing, $salary);

        $location = $this->locationFilter->check($text, $remoteType);
        $stack = $this->stackFilter->check($text);

        $flags = array_merge($salary->flags, $location->flags, $stack->flags);

        foreach ([$salary, $location, $stack] as $result) {
            if ($result->isFail()) {
                $listing->update([
                    'state' => 'filtered_out',
                    'filter_reason' => $result->reason,
                    'filter_flags' => $flags,
                ]);

                return $result;
            }
        }

        $listing->update([
            'state' => 'filtered_in',
            'filter_reason' => null,
            'filter_flags' => $flags,
        ]);

        return FilterResult::pass($flags);
    }

    private function reject(Listing $listing, FilterResult $result): FilterResult
    {
        $listing->update([
            'state' => 'filtered_out',
            'filter_reason' => $result->reason,
        ]);

        return $result;
    }

    private function persistSalary(Listing $listing, FilterResult $salary): void
    {
        $data = $salary->data;

        ListingSalary::updateOrCreate(
            ['listing_id' => $listing->id],
            [
                'tiers' => $data['tiers'] ?? [],
                'snippet' => $data['snippet'] ?? null,
                'parse_confidence' => $data['confidence'] ?? 'none',
                'status' => $salary->verdict,
                'tiered' => $salary->flags['tiered'] ?? false,
            ],
        );
    }
}
