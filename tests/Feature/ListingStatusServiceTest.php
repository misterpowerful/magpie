<?php

use App\Models\Listing;
use App\Services\ListingStatusService;

test('setting status to applied stamps applied_at and logs history', function () {
    $listing = Listing::factory()->create();

    (new ListingStatusService)->setStatus($listing, 'applied', 'ui', 'Referral from a friend');

    $listing->refresh();

    expect($listing->status)->toBe('applied');
    expect($listing->status_reason)->toBe('Referral from a friend');
    expect($listing->applied_at)->not->toBeNull();

    expect($listing->statusHistory)->toHaveCount(1);
    expect($listing->statusHistory->first()->status)->toBe('applied');
    expect($listing->statusHistory->first()->source)->toBe('ui');
});

test('setting status to not_interested does not stamp applied_at', function () {
    $listing = Listing::factory()->create();

    (new ListingStatusService)->setStatus($listing, 'not_interested', 'ui', 'Salary too low');

    $listing->refresh();

    expect($listing->status)->toBe('not_interested');
    expect($listing->applied_at)->toBeNull();
});

test('repeated status changes accumulate history rather than overwrite it', function () {
    $listing = Listing::factory()->create();
    $service = new ListingStatusService;

    $service->setStatus($listing, 'not_interested', 'ui', 'Too junior');
    $service->setStatus($listing, 'applied', 'gatsby');

    expect($listing->statusHistory()->count())->toBe(2);
});
