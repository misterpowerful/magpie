<?php

declare(strict_types=1);

use App\Support\PageAccess;
use Illuminate\Support\Facades\Http;

/**
 * @param  array<string, string>  $headers
 * @return array{verdict: string, reason: ?string, text: string}
 */
function assessPage(string $body, int $status = 200, array $headers = []): array
{
    Http::fake(['page.example.com/*' => Http::response($body, $status, $headers)]);

    return app(PageAccess::class)->assess(Http::get('https://page.example.com/jobs'));
}

function realPage(): string
{
    return '<html><body><h1>Senior PHP Engineer</h1><p>'.str_repeat('We are hiring a remote Laravel engineer to build things. ', 20).'</p></body></html>';
}

test('a normal page with real text is readable', function () {
    $result = assessPage(realPage());

    expect($result['verdict'])->toBe(PageAccess::READABLE);
    expect($result['reason'])->toBeNull();
    expect($result['text'])->toContain('Senior PHP Engineer');
});

test('401 and 403 need a browser', function (int $status) {
    expect(assessPage(realPage(), $status)['verdict'])->toBe(PageAccess::NEEDS_BROWSER);
})->with([401, 403]);

test('a Cloudflare challenge needs a browser even when served as a 503', function () {
    $challenge = '<html><head><title>Just a moment...</title></head><body><script src="/cdn-cgi/challenge-platform/x.js"></script></body></html>';

    $result = assessPage($challenge, 503);

    expect($result['verdict'])->toBe(PageAccess::NEEDS_BROWSER);
    expect($result['reason'])->toContain('Bot protection');
});

test('a cf-mitigated challenge header needs a browser', function () {
    expect(assessPage(realPage(), 403, ['cf-mitigated' => 'challenge'])['reason'])->toContain('Bot protection');
});

test('an ordinary 404 or 500 is an HTTP error, not a reason to escalate to the browser', function (int $status) {
    $result = assessPage('<html><body>Not here</body></html>', $status);

    expect($result['verdict'])->toBe(PageAccess::HTTP_ERROR);
    expect($result['reason'])->toContain((string) $status);
})->with([404, 500]);

test('sign-in wording needs a browser', function () {
    $page = '<html><body><p>Please sign in to view this job.</p><p>'.str_repeat('Filler text about the company. ', 20).'</p></body></html>';

    expect(assessPage($page)['reason'])->toContain('sign-in');
});

test('an almost empty page needs a browser', function () {
    expect(assessPage('<html><body><div>Loading</div></body></html>')['reason'])->toContain('too short');
});

test('an empty app container with only chrome text needs a browser', function () {
    $page = '<html><body><nav>Home Jobs About</nav><div id="__next"></div><p>'.str_repeat('Cookie notice and footer links. ', 12).'</p></body></html>';

    expect(assessPage($page)['reason'])->toContain('JavaScript');
});

test('a server-rendered page that also has an app container is still readable', function () {
    $page = '<html><body><div id="root"></div>'.realPage().str_repeat('<p>More job details and benefits information here.</p>', 30).'</body></html>';

    expect(assessPage($page)['verdict'])->toBe(PageAccess::READABLE);
});
