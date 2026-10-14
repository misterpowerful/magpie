<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingSalary;
use App\Models\Preference;
use App\Services\ListingFilterService;

beforeEach(function () {
    $this->service = app(ListingFilterService::class);
    $this->preferences = Preference::factory()->create();
});

test('a stub listing with an excluded title is filtered out without touching salary', function () {
    $preferences = Preference::factory()->create(['exclude_titles' => ['Junior']]);
    $listing = Listing::factory()->create(['title' => 'Junior PHP Developer', 'state' => 'new']);

    $result = $this->service->runStubFilters($listing, $preferences);

    expect($result->isFail())->toBeTrue();

    $listing->refresh();
    expect($listing->state)->toBe('filtered_out');
    expect($listing->filter_reason)->not->toBeNull();
    expect(ListingSalary::count())->toBe(0);
});

test('a stub listing that passes title and location checks stays "new", awaiting enrichment', function () {
    $listing = Listing::factory()->create(['title' => 'Senior PHP Engineer', 'location_text' => null, 'remote_type' => 'remote', 'state' => 'new']);

    $this->service->runStubFilters($listing, $this->preferences);

    $listing->refresh();
    expect($listing->state)->toBe('new');
});

test('hard filters: a salary below hard_min filters the listing out and still records the parse', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'remote',
        'raw_text' => 'We are hiring a PHP engineer. Base salary $100,000 - $110,000 per year, fully remote.',
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isFail())->toBeTrue();

    $listing->refresh();
    expect($listing->state)->toBe('filtered_out');
    expect($listing->filter_reason)->toContain('floor');

    $salary = ListingSalary::where('listing_id', $listing->id)->first();
    expect($salary)->not->toBeNull();
    expect($salary->status)->toBe('fail');
    expect($salary->parse_confidence)->toBe('high');
});

test('hard filters: a hard stack mismatch filters the listing out even with a good salary', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'remote',
        'raw_text' => 'Base salary $160,000 per year, fully remote. You must have 5+ years of Java/Spring experience.',
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isFail())->toBeTrue();

    $listing->refresh();
    expect($listing->state)->toBe('filtered_out');
});

test('hard filters: a listing on-site outside Illinois is filtered out regardless of salary', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'onsite',
        'raw_text' => 'Base salary $200,000 per year. PHP/Laravel role. Location: Austin, TX.',
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isFail())->toBeTrue();
    $listing->refresh();
    expect($listing->state)->toBe('filtered_out');
});

test('hard filters: a listing that clears every filter is filtered_in with merged flags', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'remote',
        'raw_text' => 'PHP/Laravel role. Base salary $160,000 per year, fully remote.',
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isPass())->toBeTrue();

    $listing->refresh();
    expect($listing->state)->toBe('filtered_in');
    expect($listing->filter_reason)->toBeNull();
    expect($listing->filter_flags['bonus_unknown'] ?? false)->toBeTrue();

    $salary = ListingSalary::where('listing_id', $listing->id)->first();
    expect($salary->status)->toBe('pass');
});

test('hard filters: no parseable salary does not fail and is recorded as unknown', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'remote',
        'raw_text' => 'PHP/Laravel role. Compensation is competitive and commensurate with experience.',
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isFail())->toBeFalse();

    $listing->refresh();
    expect($listing->state)->toBe('filtered_in');
    expect($listing->filter_flags['salary_unknown'] ?? false)->toBeTrue();

    $salary = ListingSalary::where('listing_id', $listing->id)->first();
    expect($salary->status)->toBe('unknown');
    expect($salary->parse_confidence)->toBe('none');
});

test('hard filters: a tiered salary passing on one tier records the tiered flag on the listing', function () {
    $listing = Listing::factory()->create([
        'state' => 'fetched',
        'remote_type' => 'remote',
        'raw_text' => "PHP/Laravel role.\nIllinois: \$150,000 - \$170,000 base salary.\nRest of US: \$120,000 - \$135,000 base salary.",
    ]);

    $result = $this->service->runHardFilters($listing, $this->preferences);

    expect($result->isPass())->toBeTrue();

    $listing->refresh();
    expect($listing->filter_flags['tiered'] ?? false)->toBeTrue();

    $salary = ListingSalary::where('listing_id', $listing->id)->first();
    expect($salary->tiered)->toBeTrue();
    expect($salary->tiers)->toHaveCount(2);
});
