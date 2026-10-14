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

test('runMany fetches every site concurrently and one failure does not block the others', function () {
    $wwrBody = file_get_contents(base_path('tests/Fixtures/wwr.rss'));
    $remotiveBody = file_get_contents(base_path('tests/Fixtures/remotive.json'));

    Http::fake([
        'weworkremotely.com/*' => Http::response($wwrBody, 200),
        'remotive.com/*' => Http::response($remotiveBody, 200),
        'down-site.example.com/*' => Http::failedConnection('Could not resolve host'),
    ]);

    $good1 = Site::factory()->create([
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'config' => ['adapter' => 'we_work_remotely'],
    ]);
    $good2 = Site::factory()->create([
        'url' => 'https://remotive.com/api/remote-jobs?category=software-dev',
        'config' => ['adapter' => 'remotive'],
    ]);
    $down = Site::factory()->create([
        'url' => 'https://down-site.example.com/feed',
        'config' => ['adapter' => 'remotive'],
    ]);

    $results = app(SiteIntakeService::class)->runMany(collect([$good1, $good2, $down]));

    expect($results[$good1->id])->toBeGreaterThan(0);
    expect($results[$good2->id])->toBeGreaterThan(0);
    expect($results[$down->id])->toBe(0);

    $good1->refresh();
    $good2->refresh();
    $down->refresh();

    expect($good1->last_status)->toBe('ok');
    expect($good2->last_status)->toBe('ok');
    expect($down->last_status)->toBe('error');

    expect(Listing::where('user_id', $good1->user_id)->count())->toBe($results[$good1->id]);
});

test('runMany still records an unresolvable adapter as an error without crashing', function () {
    $site = Site::factory()->create(['config' => ['adapter' => 'nonexistent']]);

    $results = app(SiteIntakeService::class)->runMany(collect([$site]));

    expect($results[$site->id])->toBe(0);
    $site->refresh();
    expect($site->last_status)->toBe('error');
});
