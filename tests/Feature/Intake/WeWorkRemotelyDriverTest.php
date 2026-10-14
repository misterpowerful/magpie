<?php

use App\Intake\WeWorkRemotelyDriver;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('parses company and title out of the "Company: Title" format, with the full description as raw_text', function () {
    $body = file_get_contents(base_path('tests/Fixtures/wwr.rss'));
    Http::fake(['weworkremotely.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create(['url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss']);

    $stubs = (new WeWorkRemotelyDriver)->fetch($site);

    expect($stubs)->not->toBeEmpty();
    expect($stubs[0]['company'])->not->toBeNull();
    expect($stubs[0]['title'])->not->toContain(':');
    expect($stubs[0]['remote_type'])->toBe('remote');
    expect($stubs[0]['raw_text'])->not->toContain('<');
    expect($stubs[0]['url'])->toStartWith('https://weworkremotely.com/');
});
