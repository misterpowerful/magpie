<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Run;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/runs')->assertUnauthorized();
});

test('index returns recent runs and the backlog count for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Run::factory()->create(['status' => 'done', 'started_at' => now()->subHour()]);
    Run::factory()->create(['status' => 'paused_time', 'started_at' => now()]);

    Listing::factory()->for($user)->create(['state' => 'new']);
    Listing::factory()->for($user)->create(['state' => 'filtered_in']);
    Listing::factory()->for($user)->create(['state' => 'scored']);
    Listing::factory()->for($otherUser)->create(['state' => 'new']);

    $response = $this->actingAs($user)->getJson('/api/runs')->assertOk();

    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0.status'))->toBe('paused_time');
    expect($response->json('backlog'))->toBe(2);
});
