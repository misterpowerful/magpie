<?php

declare(strict_types=1);

use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;

beforeEach(function () {
    $this->policy = new SitePolicy;
});

test('the owning user can update, delete, and run their own site', function (string $ability) {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    expect($this->policy->{$ability}($user, $site))->toBeTrue();
})->with(['update', 'delete', 'run']);

test('a different user cannot update, delete, or run someone else\'s site', function (string $ability) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $site = Site::factory()->for($otherUser)->create();

    expect($this->policy->{$ability}($user, $site))->toBeFalse();
})->with(['update', 'delete', 'run']);
