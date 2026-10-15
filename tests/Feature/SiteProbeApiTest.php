<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('probing requires authentication', function () {
    $this->postJson('/api/admin/sites/probe', ['url' => 'https://example.com'])->assertUnauthorized();
});

test('probing requires an http(s) URL', function (string $url) {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
})->with(['not a url', 'ftp://example.com/feed', 'file:///etc/passwd']);

test('an RSS feed on a known source suggests its built-in adapter', function () {
    Http::fake(['weworkremotely.com/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel></channel></rss>', 200, ['Content-Type' => 'application/rss+xml'])]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss'])
        ->assertOk()
        ->assertJsonPath('data.verdict', 'feed')
        ->assertJsonPath('data.suggested_driver', 'rss')
        ->assertJsonPath('data.suggested_adapter', 'we_work_remotely');
});

test('a feed from an unknown source says an adapter is needed', function () {
    Http::fake(['jobs.example.com/*' => Http::response('<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"></feed>', 200, ['Content-Type' => 'application/xml'])]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://jobs.example.com/feed'])
        ->assertJsonPath('data.verdict', 'feed')
        ->assertJsonPath('data.suggested_adapter', null)
        ->assertJsonPath('data.reason', fn (string $reason) => str_contains($reason, 'no adapter'));
});

test('a JSON API on a known source suggests the api driver and adapter', function () {
    Http::fake(['remotive.com/*' => Http::response(['jobs' => []], 200)]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://remotive.com/api/remote-jobs'])
        ->assertJsonPath('data.verdict', 'api')
        ->assertJsonPath('data.suggested_driver', 'api')
        ->assertJsonPath('data.suggested_adapter', 'remotive');
});

test('a readable career page suggests html and surfaces an advertised feed', function () {
    $page = '<html><head><link rel="alternate" type="application/rss+xml" href="/careers.rss"></head><body><h1>Careers</h1><p>'
        .str_repeat('We are hiring engineers across the company. ', 15).'</p></body></html>';
    Http::fake(['careers.example.com/*' => Http::response($page, 200, ['Content-Type' => 'text/html'])]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://careers.example.com/jobs'])
        ->assertJsonPath('data.verdict', 'readable')
        ->assertJsonPath('data.suggested_driver', 'html')
        ->assertJsonPath('data.feed_url', 'https://careers.example.com/careers.rss');
});

test('a walled page suggests the browser driver with the reason', function () {
    Http::fake(['www.linkedin.com/*' => Http::response('<html><body>Sign in</body></html>', 403, ['Content-Type' => 'text/html'])]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://www.linkedin.com/jobs/search'])
        ->assertJsonPath('data.verdict', 'needs_browser')
        ->assertJsonPath('data.suggested_driver', 'browser')
        ->assertJsonPath('data.http_status', 403)
        ->assertJsonPath('data.reason', fn (string $reason) => str_contains($reason, 'Claude in Chrome'));
});

test('a 404 is reported as unreachable with no suggestion', function () {
    Http::fake(['careers.example.com/*' => Http::response('nope', 404)]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://careers.example.com/missing'])
        ->assertJsonPath('data.verdict', 'unreachable')
        ->assertJsonPath('data.suggested_driver', null);
});

test('a connection failure is reported as unreachable', function () {
    Http::fake(['nowhere.invalid/*' => Http::failedConnection('Could not resolve host')]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/sites/probe', ['url' => 'https://nowhere.invalid/jobs'])
        ->assertJsonPath('data.verdict', 'unreachable')
        ->assertJsonPath('data.reason', fn (string $reason) => str_contains($reason, 'Could not resolve host'));
});
