<?php

declare(strict_types=1);

use App\Intake\RemotiveDriver;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('parses Remotive jobs JSON', function () {
    $body = file_get_contents(base_path('tests/Fixtures/remotive.json'));
    Http::fake(['remotive.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create(['url' => 'https://remotive.com/api/remote-jobs?category=software-dev']);

    $stubs = (new RemotiveDriver)->fetch($site);

    expect($stubs)->not->toBeEmpty();
    expect($stubs[0]['company'])->not->toBeNull();
    expect($stubs[0]['title'])->not->toBeEmpty();
    expect($stubs[0]['raw_text'])->not->toContain('<p');
    expect($stubs[0]['url'])->toStartWith('https://remotive.com/');
});
