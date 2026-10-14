<?php

use App\Intake\LaraJobsDriver;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('parses the job: namespace fields', function () {
    $body = file_get_contents(base_path('tests/Fixtures/larajobs.rss'));
    Http::fake(['larajobs.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create(['url' => 'https://larajobs.com/feed']);

    $stubs = (new LaraJobsDriver)->fetch($site);

    expect($stubs)->not->toBeEmpty();
    expect($stubs[0]['company'])->not->toBeNull();
    expect($stubs[0]['title'])->not->toBeEmpty();
    expect($stubs[0]['url'])->toStartWith('https://larajobs.com/job/');
});

test('guesses remote_type from the job location when it mentions remote', function () {
    $body = file_get_contents(base_path('tests/Fixtures/larajobs.rss'));
    Http::fake(['larajobs.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create(['url' => 'https://larajobs.com/feed']);

    $stubs = (new LaraJobsDriver)->fetch($site);
    $remoteStub = collect($stubs)->first(fn ($s) => $s['location_text'] && stripos($s['location_text'], 'remote') !== false);

    expect($remoteStub)->not->toBeNull();
    expect($remoteStub['remote_type'])->toBe('remote');
});
