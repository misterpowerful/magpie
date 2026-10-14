<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\Preference;
use App\Services\ListingScoringService;
use Tests\Support\FakeLlmDriver;

test('a filtered_in listing gets scored and moves to the scored state', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $driver = new FakeLlmDriver('Strong');
    $score = (new ListingScoringService($driver))->score($listing, $preferences);

    expect($score)->not->toBeNull();
    expect($score->rating)->toBe('Strong');
    expect($score->commentary)->not->toBeNull();

    $listing->refresh();
    expect($listing->state)->toBe('scored');
    expect($driver->calls)->toBe(1);
});

test('a Moderate/Weak rating never carries commentary', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $score = (new ListingScoringService(new FakeLlmDriver('Weak')))->score($listing, $preferences);

    expect($score->commentary)->toBeNull();
});

test('a listing that has not cleared the filter pipeline is never scored', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'new']);

    $driver = new FakeLlmDriver;
    $score = (new ListingScoringService($driver))->score($listing, $preferences);

    expect($score)->toBeNull();
    expect($driver->calls)->toBe(0);
});

test('an applied listing is never rescored even if it was previously filtered_in', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in', 'status' => 'applied']);

    $driver = new FakeLlmDriver;
    $score = (new ListingScoringService($driver))->score($listing, $preferences);

    expect($score)->toBeNull();
    expect($driver->calls)->toBe(0);
});

test('a not_interested listing is never rescored', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in', 'status' => 'not_interested']);

    $driver = new FakeLlmDriver;
    (new ListingScoringService($driver))->score($listing, $preferences);

    expect($driver->calls)->toBe(0);
});

test('rescoring is skipped when neither preferences nor resume changed', function () {
    $preferences = Preference::factory()->create(['resume_text' => 'Senior PHP engineer.']);
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $driver = new FakeLlmDriver;
    $service = new ListingScoringService($driver);

    $service->score($listing, $preferences);
    $listing->update(['state' => 'filtered_in']); // simulate a later run re-considering it
    $service->score($listing, $preferences);

    expect($driver->calls)->toBe(1);
});

test('a changed resume forces a rescore', function () {
    $preferences = Preference::factory()->create(['resume_text' => 'Senior PHP engineer.']);
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $driver = new FakeLlmDriver;
    $service = new ListingScoringService($driver);

    $service->score($listing, $preferences);

    $preferences->update(['resume_text' => 'Staff PHP engineer, now with Go experience.']);
    $listing->update(['state' => 'filtered_in']);
    $service->score($listing, $preferences);

    expect($driver->calls)->toBe(2);
});

test('changed preferences force a rescore', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $driver = new FakeLlmDriver;
    $service = new ListingScoringService($driver);

    $service->score($listing, $preferences);

    $preferences->update(['hard_min' => 160000]);
    $listing->update(['state' => 'filtered_in']);
    $service->score($listing, $preferences);

    expect($driver->calls)->toBe(2);
});

test('scoring persists reasons and flags as arrays', function () {
    $preferences = Preference::factory()->create();
    $listing = Listing::factory()->create(['state' => 'filtered_in']);

    $score = (new ListingScoringService(new FakeLlmDriver))->score($listing, $preferences);

    expect(ListingScore::find($score->id)->reasons)->toBe(['Good stack fit']);
});
