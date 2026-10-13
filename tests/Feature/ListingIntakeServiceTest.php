<?php

use App\Models\User;
use App\Services\ListingIntakeService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = new ListingIntakeService;
});

test('creates a new listing stub with state and status "new"', function () {
    $listing = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://example.com/jobs/1?utm_source=indeed',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    expect($listing->wasRecentlyCreated)->toBeTrue();
    expect($listing->canonical_url)->toBe('https://example.com/jobs/1');
    expect($listing->state)->toBe('new');
    expect($listing->status)->toBe('new');
});

test('the same canonical URL is deduped even with different tracking params', function () {
    $first = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://example.com/jobs/1?utm_source=indeed',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    $second = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://example.com/jobs/1?utm_source=linkedin',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    expect($second->id)->toBe($first->id);
    expect($second->wasRecentlyCreated)->toBeFalse();
});

test('the same job posted under a different URL is deduped on company+title', function () {
    $first = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://board-a.example.com/listing/1',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    $second = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://board-b.example.com/listing/999',
        'company' => 'acme co',
        'title' => '  Senior PHP Engineer  ',
    ]);

    expect($second->id)->toBe($first->id);
});

test('a different company with the same title is not deduped', function () {
    $first = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://board-a.example.com/listing/1',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    $second = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://board-b.example.com/listing/2',
        'company' => 'Other Co',
        'title' => 'Senior PHP Engineer',
    ]);

    expect($second->id)->not->toBe($first->id);
});

test('the same canonical URL for a different user is not deduped', function () {
    $otherUser = User::factory()->create();

    $first = $this->service->firstOrCreateFromStub($this->user->id, [
        'url' => 'https://example.com/jobs/1',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    $second = $this->service->firstOrCreateFromStub($otherUser->id, [
        'url' => 'https://example.com/jobs/1',
        'company' => 'Acme Co',
        'title' => 'Senior PHP Engineer',
    ]);

    expect($second->id)->not->toBe($first->id);
});
