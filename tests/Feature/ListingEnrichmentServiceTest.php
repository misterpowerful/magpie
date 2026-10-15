<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Services\ListingEnrichmentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(ListingEnrichmentService::class);
});

test('a successful fetch with a real body moves the listing to fetched', function () {
    $html = '<html><body><h1>Senior PHP Engineer</h1>'
        .'<p>'.str_repeat('We need a remote PHP and Laravel engineer. ', 10).'</p>'
        .'<script>trackPageView();</script></body></html>';

    Http::fake(['example.com/*' => Http::response($html, 200)]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('fetched');

    $listing->refresh();
    expect($listing->state)->toBe('fetched');
    expect($listing->raw_text)->toContain('Senior PHP Engineer');
    expect($listing->raw_text)->not->toContain('trackPageView');
});

test('a 401 or 403 response is treated as a login wall', function (int $status) {
    Http::fake(['example.com/*' => Http::response('', $status)]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('needs_browser');

    $listing->refresh();
    expect($listing->state)->toBe('needs_browser');
    expect($listing->filter_reason)->toContain((string) $status);
})->with([401, 403]);

test('page content that reads as a sign-in wall moves the listing to needs_browser even with a 200 and plenty of text', function () {
    $html = '<html><body><h1>Job posting</h1>'
        .'<p>'.str_repeat('Please sign in to view this job and its full description. ', 10).'</p>'
        .'</body></html>';

    Http::fake(['example.com/*' => Http::response($html, 200)]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('needs_browser');

    $listing->refresh();
    expect($listing->state)->toBe('needs_browser');
    expect($listing->filter_reason)->toContain('sign-in');
});

test('a 200 response with an empty JS-rendered shell moves the listing to needs_browser', function () {
    $html = '<html><body><div id="app"></div><script>bootApp();</script></body></html>';

    Http::fake(['example.com/*' => Http::response($html, 200)]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('needs_browser');

    $listing->refresh();
    expect($listing->state)->toBe('needs_browser');
    expect($listing->filter_reason)->toContain('too short');
});

test('a server error marks the listing enrichment_failed, not needs_browser', function () {
    Http::fake(['example.com/*' => Http::response('Internal Server Error', 500)]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('enrichment_failed');

    $listing->refresh();
    expect($listing->state)->toBe('enrichment_failed');
    expect($listing->filter_reason)->toContain('500');
});

test('a connection failure marks the listing enrichment_failed', function () {
    Http::fake(['example.com/*' => Http::failedConnection('Could not resolve host')]);

    $listing = Listing::factory()->create(['canonical_url' => 'https://example.com/jobs/123', 'state' => 'new']);

    $result = $this->service->enrich($listing);

    expect($result)->toBe('enrichment_failed');

    $listing->refresh();
    expect($listing->state)->toBe('enrichment_failed');
    expect($listing->filter_reason)->toContain('Connection error');
});

test('a bot-protection challenge served as a 503 moves the listing to needs_browser, not enrichment_failed', function () {
    Http::fake(['*' => Http::response('<html><head><title>Just a moment...</title></head><body></body></html>', 503)]);
    $listing = Listing::factory()->create(['state' => 'new', 'canonical_url' => 'https://jobs.example.com/1']);

    app(ListingEnrichmentService::class)->enrich($listing);

    expect($listing->fresh()->state)->toBe('needs_browser');
});

test('a 404 posting is enrichment_failed, not needs_browser', function () {
    Http::fake(['*' => Http::response('gone', 404)]);
    $listing = Listing::factory()->create(['state' => 'new', 'canonical_url' => 'https://jobs.example.com/1']);

    app(ListingEnrichmentService::class)->enrich($listing);

    expect($listing->fresh()->state)->toBe('enrichment_failed');
});
