<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Site;
use App\Services\SiteIntakeService;
use Illuminate\Support\Facades\Http;

test('a successful run creates listings and marks the site ok', function () {
    $body = file_get_contents(base_path('tests/Fixtures/wwr.rss'));
    Http::fake(['weworkremotely.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create([
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'config' => ['adapter' => 'we_work_remotely'],
    ]);

    $count = app(SiteIntakeService::class)->run($site);

    expect($count)->toBeGreaterThan(0);
    expect(Listing::where('user_id', $site->user_id)->count())->toBe($count);

    $site->refresh();
    expect($site->last_status)->toBe('ok');
    expect($site->last_run_at)->not->toBeNull();
});

test('running the same site twice does not duplicate listings', function () {
    $body = file_get_contents(base_path('tests/Fixtures/wwr.rss'));
    Http::fake(['weworkremotely.com/*' => Http::response($body, 200)]);

    $site = Site::factory()->create([
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'config' => ['adapter' => 'we_work_remotely'],
    ]);

    $service = app(SiteIntakeService::class);
    $service->run($site);
    $countAfterFirst = Listing::count();
    $service->run($site);

    expect(Listing::count())->toBe($countAfterFirst);
});

test('a failing driver is recorded as an error without crashing', function () {
    Http::fake(['weworkremotely.com/*' => Http::response('not xml', 500)]);

    $site = Site::factory()->create([
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'config' => ['adapter' => 'we_work_remotely'],
    ]);

    $count = app(SiteIntakeService::class)->run($site);

    expect($count)->toBe(0);

    $site->refresh();
    expect($site->last_status)->toBe('error');
});

test('an unresolvable adapter is also recorded as an error, not a crash', function () {
    $site = Site::factory()->create(['config' => ['adapter' => 'nonexistent']]);

    $count = app(SiteIntakeService::class)->run($site);

    expect($count)->toBe(0);
    $site->refresh();
    expect($site->last_status)->toBe('error');
});
