<?php

declare(strict_types=1);

use App\Contracts\LlmDriver;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\Run;
use App\Models\User;
use Tests\Support\FakeLlmDriver;
use Tests\Support\FakeMailSource;

beforeEach(function () {
    $this->app->instance(MailSource::class, new FakeMailSource);
    $this->app->instance(LlmDriver::class, new FakeLlmDriver);
});

test('catch-up is skipped and no Run is created when there is no backlog', function () {
    $this->artisan('magpie:catch-up')->assertSuccessful();

    expect(Run::count())->toBe(0);
});

test('catch-up runs a sweep when there is a backlog', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);
    Listing::factory()->for($user)->create(['state' => 'new']);

    $this->artisan('magpie:catch-up')->assertSuccessful();

    expect(Run::count())->toBe(1);
    expect(Run::first()->trigger)->toBe('catchup');
});

test('scored and filtered_out listings do not count as backlog', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['state' => 'scored']);
    Listing::factory()->for($user)->create(['state' => 'filtered_out']);
    Listing::factory()->for($user)->create(['state' => 'needs_browser']);
    Listing::factory()->for($user)->create(['state' => 'enrichment_failed']);

    $this->artisan('magpie:catch-up')->assertSuccessful();

    expect(Run::count())->toBe(0);
});
