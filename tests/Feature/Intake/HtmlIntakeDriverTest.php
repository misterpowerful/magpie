<?php

declare(strict_types=1);

use App\Intake\HtmlIntakeDriver;
use App\Models\Site;
use Illuminate\Support\Facades\Http;

test('parses listings from a career page using configured XPath selectors', function () {
    $html = <<<'HTML'
        <html><body>
            <div class="job">
                <a class="job-link" href="/careers/senior-php-engineer">Senior PHP Engineer</a>
                <span class="company">Acme Inc</span>
                <span class="location">Remote - US</span>
            </div>
            <div class="job">
                <a class="job-link" href="https://other-domain.com/jobs/backend-dev">Backend Developer</a>
                <span class="company">Widgets Co</span>
                <span class="location">Chicago, IL</span>
            </div>
            <div class="job">
                <!-- malformed entry: no link, should be skipped -->
                <span class="company">No Link Co</span>
            </div>
        </body></html>
        HTML;

    Http::fake(['careers.example.com/*' => Http::response($html, 200)]);

    $site = Site::factory()->create([
        'url' => 'https://careers.example.com/jobs',
        'config' => [
            'adapter' => 'html',
            'selectors' => [
                'list_item' => "//div[@class='job']",
                'link' => ".//a[@class='job-link']/@href",
                'title' => ".//a[@class='job-link']",
                'company' => ".//span[@class='company']",
                'location' => ".//span[@class='location']",
            ],
        ],
    ]);

    $stubs = parseSite(new HtmlIntakeDriver, $site);

    expect($stubs)->toHaveCount(2);

    expect($stubs[0]['url'])->toBe('https://careers.example.com/careers/senior-php-engineer');
    expect($stubs[0]['title'])->toBe('Senior PHP Engineer');
    expect($stubs[0]['company'])->toBe('Acme Inc');
    expect($stubs[0]['remote_type'])->toBe('remote');
    expect($stubs[0]['raw_text'])->toBeNull();

    expect($stubs[1]['url'])->toBe('https://other-domain.com/jobs/backend-dev');
    expect($stubs[1]['company'])->toBe('Widgets Co');
});

test('missing optional selectors (company, location) yield null rather than failing', function () {
    $html = '<div class="job"><a href="/jobs/1">Only Title</a></div>';

    Http::fake(['careers.example.com/*' => Http::response($html, 200)]);

    $site = Site::factory()->create([
        'url' => 'https://careers.example.com/jobs',
        'config' => [
            'adapter' => 'html',
            'selectors' => [
                'list_item' => "//div[@class='job']",
                'link' => './/a/@href',
                'title' => './/a',
            ],
        ],
    ]);

    $stubs = parseSite(new HtmlIntakeDriver, $site);

    expect($stubs)->toHaveCount(1);
    expect($stubs[0]['company'])->toBeNull();
    expect($stubs[0]['location_text'])->toBeNull();
});

test('a site missing required selectors throws rather than silently returning nothing', function () {
    Http::fake(['careers.example.com/*' => Http::response('<html><body></body></html>', 200)]);

    $site = Site::factory()->create([
        'url' => 'https://careers.example.com/jobs',
        'config' => ['adapter' => 'html', 'selectors' => ['list_item' => "//div[@class='job']"]],
    ]);

    parseSite(new HtmlIntakeDriver, $site);
})->throws(RuntimeException::class);
