<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\ListingSource;
use App\Models\LlmCall;
use App\Models\Run;
use App\Models\Site;
use App\Models\User;

function insightsScore(Listing $listing, string $rating): void
{
    ListingScore::create([
        'listing_id' => $listing->id, 'rating' => $rating, 'reasons' => [], 'flags' => [], 'commentary' => null,
        'prefs_hash' => 'test', 'resume_hash' => 'test', 'model' => 'claude-sonnet-5', 'scored_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insightsCall(array $overrides = []): LlmCall
{
    return LlmCall::create(array_merge([
        'context' => 'listing:1', 'model' => 'claude-sonnet-5', 'input_tokens' => 100, 'output_tokens' => 10,
        'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0, 'cost_usd' => 0.01,
        'duration_ms' => 500, 'outcome' => 'ok', 'created_at' => now()->subHour(),
    ], $overrides));
}

test('insights endpoints require authentication', function () {
    $this->getJson('/api/insights/cost')->assertUnauthorized();
    $this->getJson('/api/insights/sources')->assertUnauthorized();
    $this->getJson('/api/insights/ratings')->assertUnauthorized();
});

test('cost insights total the range and break it down per run, task, and model', function () {
    $older = Run::factory()->create(['started_at' => now()->subDays(5), 'ended_at' => now()->subDays(5)->addHour()]);
    $newer = Run::factory()->create(['started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addHour()]);
    Run::factory()->create(['started_at' => now()->subDays(40), 'ended_at' => now()->subDays(40)->addHour()]);

    insightsCall(['run_id' => $older->id, 'cost_usd' => 0.10, 'created_at' => now()->subDays(5), 'cache_read_input_tokens' => 400]);
    insightsCall(['run_id' => $newer->id, 'cost_usd' => 0.20, 'created_at' => now()->subDays(2), 'model' => 'claude-haiku-5']);
    insightsCall(['run_id' => $newer->id, 'cost_usd' => 0.05, 'created_at' => now()->subDays(2), 'context' => 'generic', 'model' => 'claude-haiku-5', 'outcome' => 'cli_error']);
    // Outside any run: in the day-range totals, but not in the per-run series.
    insightsCall(['cost_usd' => 0.01, 'created_at' => now()->subDay()]);

    $response = $this->actingAs(User::factory()->create())->getJson('/api/insights/cost?range=7d')->assertOk();

    $totals = $response->json('data.totals');
    expect($totals['calls'])->toBe(4);
    expect($totals['failed_calls'])->toBe(1);
    expect(round($totals['cost_usd'], 2))->toBe(0.36);
    expect($totals['cache_read_input_tokens'])->toBe(400);

    $runs = $response->json('data.runs');
    expect(array_column($runs, 'run_id'))->toBe([$older->id, $newer->id]);
    expect(array_column($runs, 'calls'))->toBe([1, 2]);
    expect(round($runs[1]['cost_usd'], 2))->toBe(0.25);

    $byTask = collect($response->json('data.by_task'))->keyBy('label');
    expect($byTask->keys()->all())->toBe(['Scoring', 'Email parsing']);
    expect($byTask['Scoring']['calls'])->toBe(3);

    expect(array_column($response->json('data.by_model'), 'label'))->toBe(['claude-haiku-5', 'claude-sonnet-5']);
});

test('cost insights for last_run cover only that run', function () {
    $previous = Run::factory()->create(['started_at' => now()->subDays(3), 'ended_at' => now()->subDays(3)->addHour()]);
    $latest = Run::factory()->create(['started_at' => now()->subHours(2), 'ended_at' => now()->subHour()]);

    insightsCall(['run_id' => $previous->id, 'cost_usd' => 0.50]);
    insightsCall(['run_id' => $latest->id, 'cost_usd' => 0.07]);
    insightsCall(['cost_usd' => 0.90, 'created_at' => now()->subMinutes(90)]);

    $response = $this->actingAs(User::factory()->create())->getJson('/api/insights/cost?range=last_run')->assertOk();

    expect($response->json('data.totals.calls'))->toBe(1);
    expect(array_column($response->json('data.runs'), 'run_id'))->toBe([$latest->id]);
});

test('source insights break each source down by rating', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create(['name' => 'Remotive', 'driver' => 'rss']);

    foreach ([['Strong', $site->id], ['Weak', $site->id], [null, $site->id], ['Strong', null]] as [$rating, $siteId]) {
        $listing = Listing::factory()->for($user)->create(['first_seen_at' => now()->subDay()]);
        ListingSource::create(['listing_id' => $listing->id, 'site_id' => $siteId, 'first_seen_at' => now()->subDay()]);

        if ($rating) {
            insightsScore($listing, $rating);
        }
    }

    $data = collect($this->actingAs($user)->getJson('/api/insights/sources?range=7d')->assertOk()->json('data'))->keyBy('source');

    expect($data['Remotive'])->toBe([
        'source' => 'Remotive', 'bucket' => 'feeds', 'Strong' => 1, 'Moderate' => 0, 'Weak' => 1, 'Unscored' => 1, 'total' => 3,
    ]);
    expect($data['Email']['Strong'])->toBe(1);
});

test('rating insights give each run in range the mix of listings it pulled in', function () {
    $user = User::factory()->create();
    $first = Run::factory()->create(['started_at' => now()->subDays(4), 'ended_at' => now()->subDays(4)->addHour()]);
    $second = Run::factory()->create(['started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);

    insightsScore(Listing::factory()->for($user)->create(['first_seen_at' => now()->subDays(4)->addMinutes(10)]), 'Strong');
    Listing::factory()->for($user)->create(['first_seen_at' => now()->subDay()->addMinutes(5)]);
    insightsScore(Listing::factory()->for($user)->create(['first_seen_at' => now()->subDay()->addMinutes(20)]), 'Weak');
    // Between runs (e.g. a manual site run): belongs to neither.
    Listing::factory()->for($user)->create(['first_seen_at' => now()->subDays(2)]);

    $runs = $this->actingAs($user)->getJson('/api/insights/ratings?range=7d')->assertOk()->json('data.runs');

    expect($runs)->toHaveCount(2);
    expect($runs[0])->toMatchArray(['run_id' => $first->id, 'Strong' => 1, 'Moderate' => 0, 'Weak' => 0, 'Unscored' => 0]);
    expect($runs[1])->toMatchArray(['run_id' => $second->id, 'Strong' => 0, 'Moderate' => 0, 'Weak' => 1, 'Unscored' => 1]);
});

test('30d is accepted as a range', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/stats/usage?range=30d')->assertOk()
        ->assertJsonPath('meta.range', '30d');
});
