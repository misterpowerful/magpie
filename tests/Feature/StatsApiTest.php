<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\ListingSource;
use App\Models\LlmCall;
use App\Models\Site;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/stats/sources')->assertUnauthorized();
    $this->getJson('/api/stats/ratings')->assertUnauthorized();
    $this->getJson('/api/stats/usage')->assertUnauthorized();
});

test('source stats group by site name, default to the last day, and fold a null site_id into Email', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create(['name' => 'We Work Remotely']);

    $inRange = Listing::factory()->for($user)->create(['first_seen_at' => now()->subHours(2)]);
    $outOfRange = Listing::factory()->for($user)->create(['first_seen_at' => now()->subDays(3)]);

    ListingSource::create(['listing_id' => $inRange->id, 'site_id' => $site->id, 'first_seen_at' => now()->subHours(2)]);
    ListingSource::create(['listing_id' => $outOfRange->id, 'site_id' => $site->id, 'first_seen_at' => now()->subDays(3)]);

    $emailListing = Listing::factory()->for($user)->create(['first_seen_at' => now()->subHour()]);
    ListingSource::create(['listing_id' => $emailListing->id, 'site_id' => null, 'first_seen_at' => now()->subHour()]);

    $response = $this->actingAs($user)->getJson('/api/stats/sources')->assertOk();
    $data = collect($response->json('data'))->keyBy('source');

    expect($data['We Work Remotely']['count'])->toBe(1);
    expect($data['Email']['count'])->toBe(1);
});

test('source stats respect the 7d range toggle', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create(['name' => 'Remotive']);

    $listing = Listing::factory()->for($user)->create(['first_seen_at' => now()->subDays(5)]);
    ListingSource::create(['listing_id' => $listing->id, 'site_id' => $site->id, 'first_seen_at' => now()->subDays(5)]);

    $this->actingAs($user)->getJson('/api/stats/sources')->assertOk()
        ->assertJsonCount(0, 'data');

    $response = $this->actingAs($user)->getJson('/api/stats/sources?range=7d')->assertOk();
    expect(collect($response->json('data'))->firstWhere('source', 'Remotive')['count'])->toBe(1);
});

function makeScore(Listing $listing, string $rating): ListingScore
{
    return ListingScore::create([
        'listing_id' => $listing->id,
        'rating' => $rating,
        'reasons' => [],
        'flags' => [],
        'commentary' => null,
        'prefs_hash' => 'test',
        'resume_hash' => 'test',
        'model' => 'claude-sonnet-5',
        'scored_at' => now(),
    ]);
}

test('rating stats break listings down by Strong/Moderate/Weak/Unscored', function () {
    $user = User::factory()->create();

    $strong = Listing::factory()->for($user)->create(['first_seen_at' => now()->subHour()]);
    makeScore($strong, 'Strong');

    $moderate = Listing::factory()->for($user)->create(['first_seen_at' => now()->subHour()]);
    makeScore($moderate, 'Moderate');

    Listing::factory()->for($user)->create(['first_seen_at' => now()->subHour()]); // unscored

    // Out of the default 1d range — must not be counted.
    $oldStrong = Listing::factory()->for($user)->create(['first_seen_at' => now()->subDays(3)]);
    makeScore($oldStrong, 'Strong');

    $response = $this->actingAs($user)->getJson('/api/stats/ratings')->assertOk();

    expect($response->json('data'))->toBe([
        'Strong' => 1,
        'Moderate' => 1,
        'Weak' => 0,
        'Unscored' => 1,
    ]);
});

test('usage stats sum cost and tokens across llm_calls in range, independent of any user', function () {
    LlmCall::create([
        'context' => 'listing:1', 'model' => 'claude-sonnet-5', 'input_tokens' => 100, 'output_tokens' => 50,
        'cost_usd' => 0.05, 'duration_ms' => 1000, 'outcome' => 'ok', 'created_at' => now()->subHour(),
    ]);
    LlmCall::create([
        'context' => 'listing:2', 'model' => 'claude-sonnet-5', 'input_tokens' => 200, 'output_tokens' => 75,
        'cost_usd' => 0.08, 'duration_ms' => 1200, 'outcome' => 'ok', 'created_at' => now()->subHour(),
    ]);
    LlmCall::create([
        'context' => 'listing:3', 'model' => 'claude-sonnet-5', 'input_tokens' => 300, 'output_tokens' => 90,
        'cost_usd' => 0.09, 'duration_ms' => 900, 'outcome' => 'ok', 'created_at' => now()->subDays(3),
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->getJson('/api/stats/usage')->assertOk();

    expect($response->json('data'))->toBe([
        'calls' => 2,
        'cost_usd' => 0.13,
        'input_tokens' => 300,
        'output_tokens' => 125,
    ]);
});
