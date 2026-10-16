<?php

declare(strict_types=1);

use App\Intake\RemoteOkDriver;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('parses Remote OK jobs JSON, skipping the legal/metadata banner entry', function () {
    $body = file_get_contents(base_path('tests/Fixtures/remoteok.json'));
    Http::fake(['remoteok.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create(['url' => 'https://remoteok.com/api']);

    $stubs = parseSite(new RemoteOkDriver, $site);

    expect($stubs)->not->toBeEmpty();

    foreach ($stubs as $stub) {
        expect($stub['title'])->not->toBeEmpty();
    }
});
