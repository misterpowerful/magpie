<?php

declare(strict_types=1);

use App\Contracts\LlmDriver;
use App\Inbox\MailSource;
use App\Llm\LlmRateLimitedException;
use App\Models\Listing;
use App\Models\ListingSalary;
use App\Models\Preference;
use App\Models\User;
use App\Services\RunOrchestratorService;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeLlmDriver;
use Tests\Support\FakeMailSource;

function makeAdminUser(): User
{
    return User::factory()->create(['email' => config('magpie.admin_email')]);
}

beforeEach(function () {
    $this->app->instance(MailSource::class, new FakeMailSource);
});

test('a full pipeline sweep advances a listing from new all the way to scored', function () {
    $user = makeAdminUser();
    Preference::factory()->create(['user_id' => $user->id]);

    $html = '<html><body><h1>Senior PHP Engineer</h1><p>'
        .str_repeat('We need a remote PHP and Laravel engineer. ', 10)
        .'</p></body></html>';
    Http::fake(['example.com/*' => Http::response($html, 200)]);

    $listing = Listing::factory()->for($user)->create([
        'state' => 'new',
        'title' => 'Senior PHP Engineer',
        'location_text' => null,
        'remote_type' => 'remote',
    ]);

    $this->app->instance(LlmDriver::class, new FakeLlmDriver('Strong'));

    $run = app(RunOrchestratorService::class)->run('schedule');

    expect($run->status)->toBe('done');
    expect($run->trigger)->toBe('schedule');
    expect($run->ended_at)->not->toBeNull();

    $listing->refresh();
    expect($listing->state)->toBe('scored');
    expect($listing->score)->not->toBeNull();
    expect($listing->score->rating)->toBe('Strong');
});

test('a listing rejected by stub filters never reaches enrichment or scoring', function () {
    $user = makeAdminUser();
    Preference::factory()->create(['user_id' => $user->id, 'exclude_titles' => ['Intern']]);

    $listing = Listing::factory()->for($user)->create(['state' => 'new', 'title' => 'Summer Intern']);

    $driver = new FakeLlmDriver;
    $this->app->instance(LlmDriver::class, $driver);

    app(RunOrchestratorService::class)->run('schedule');

    $listing->refresh();
    expect($listing->state)->toBe('filtered_out');
    expect($driver->calls)->toBe(0);
});

test('resuming a run never rescopes a listing that already finished a stage', function () {
    $user = makeAdminUser();
    Preference::factory()->create(['user_id' => $user->id]);

    $html = '<html><body><p>'.str_repeat('Plenty of real job description text here. ', 10).'</p></body></html>';
    Http::fake(['example.com/*' => Http::response($html, 200)]);

    Listing::factory()->for($user)->create(['state' => 'new', 'remote_type' => 'remote']);

    $driver = new FakeLlmDriver('Strong');
    $this->app->instance(LlmDriver::class, $driver);

    app(RunOrchestratorService::class)->run('schedule');
    expect($driver->calls)->toBe(1);

    // A second sweep should not re-score the already-scored listing.
    app(RunOrchestratorService::class)->run('schedule');
    expect($driver->calls)->toBe(1);
});

test('a global time cap of 0 stops the run before it does any work and marks it paused_time', function () {
    config(['magpie.run.time_cap_minutes' => -1]);

    $user = makeAdminUser();
    Preference::factory()->create(['user_id' => $user->id]);
    $listing = Listing::factory()->for($user)->create(['state' => 'new']);

    $this->app->instance(LlmDriver::class, new FakeLlmDriver);

    $run = app(RunOrchestratorService::class)->run('schedule');

    expect($run->status)->toBe('paused_time');
    expect($run->stage_stats)->toBe([]);

    $listing->refresh();
    expect($listing->state)->toBe('new');
});

test('a rate limit during scoring halts the run and marks it paused_rate_limited', function () {
    $user = makeAdminUser();
    $preferences = Preference::factory()->create(['user_id' => $user->id]);

    $listing = Listing::factory()->for($user)->create(['state' => 'filtered_in']);

    $driver = new FakeLlmDriver;
    $driver->throwOnScore = new LlmRateLimitedException('rate limited');
    $this->app->instance(LlmDriver::class, $driver);

    $run = app(RunOrchestratorService::class)->run('schedule');

    expect($run->status)->toBe('paused_rate_limited');

    $listing->refresh();
    expect($listing->state)->toBe('filtered_in');
});

test('scoring survivors are ordered best-candidates-first: a confirmed high-confidence salary parse is scored before an unknown one', function () {
    $user = makeAdminUser();
    Preference::factory()->create(['user_id' => $user->id]);

    $unknownFirst = Listing::factory()->for($user)->create(['state' => 'filtered_in', 'first_seen_at' => now()->subMinutes(5)]);
    ListingSalary::create(['listing_id' => $unknownFirst->id, 'parse_confidence' => 'none']);

    $highConfidenceSecond = Listing::factory()->for($user)->create(['state' => 'filtered_in', 'first_seen_at' => now()]);
    ListingSalary::create(['listing_id' => $highConfidenceSecond->id, 'parse_confidence' => 'high']);

    $driver = new FakeLlmDriver;
    $this->app->instance(LlmDriver::class, $driver);

    app(RunOrchestratorService::class)->run('schedule');

    expect($driver->scoredListingIds)->toBe([$highConfidenceSecond->id, $unknownFirst->id]);
});

test('a run fails cleanly and is recorded as failed when no admin user exists', function () {
    $run = app(RunOrchestratorService::class)->run('schedule');

    expect($run->status)->toBe('failed');
});

test('catchup and schedule triggers are both recorded on the Run row', function () {
    makeAdminUser();

    $run = app(RunOrchestratorService::class)->run('catchup');

    expect($run->trigger)->toBe('catchup');
});
