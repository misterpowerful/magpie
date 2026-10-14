<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\User;

test('unauthenticated requests are rejected', function () {
    $listing = Listing::factory()->create();

    $this->getJson('/api/listings')->assertUnauthorized();
    $this->patchJson("/api/listings/{$listing->id}/status", ['status' => 'applied'])->assertUnauthorized();
});

test('index only returns the authenticated user\'s listings, newest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $older = Listing::factory()->for($user)->create(['first_seen_at' => now()->subDay()]);
    $newer = Listing::factory()->for($user)->create(['first_seen_at' => now()]);
    Listing::factory()->for($otherUser)->create();

    $response = $this->actingAs($user)->getJson('/api/listings')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids->all())->toBe([$newer->id, $older->id]);
});

test('a user can mark their own listing applied with a reason', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->patchJson("/api/listings/{$listing->id}/status", [
            'status' => 'applied',
            'reason' => 'Great fit',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.status_reason', 'Great fit');

    expect($listing->statusHistory()->count())->toBe(1);
});

test('a user cannot change another user\'s listing status', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $listing = Listing::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->patchJson("/api/listings/{$listing->id}/status", ['status' => 'applied'])
        ->assertForbidden();
});

test('status must be a known value', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->patchJson("/api/listings/{$listing->id}/status", ['status' => 'bogus'])
        ->assertUnprocessable();
});

test('status cannot be set back to "new" through this endpoint', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    $this->actingAs($user)
        ->patchJson("/api/listings/{$listing->id}/status", ['status' => 'new'])
        ->assertUnprocessable();
});
