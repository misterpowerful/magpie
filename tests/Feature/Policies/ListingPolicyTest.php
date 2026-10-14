<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;
use App\Policies\ListingPolicy;

beforeEach(function () {
    $this->policy = new ListingPolicy;
});

test('the owning user can update their own listing', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    expect($this->policy->update($user, $listing))->toBeTrue();
});

test('a different user cannot update someone else\'s listing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $listing = Listing::factory()->for($otherUser)->create();

    expect($this->policy->update($user, $listing))->toBeFalse();
});
