<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;

/**
 * The one place that decides whether a fetched page can be read by a
 * plain server-side request, or needs a real (logged-in, JS-running)
 * browser — the Claude in Chrome tier. Shared by listing enrichment,
 * the Add Site URL check, and html site runs, so all three agree on
 * what "needs a browser" means.
 *
 * Only judges pages that came back; connection failures and 5xx
 * errors are the caller's to handle (they're "unreachable", not
 * "needs a browser").
 */
final class PageAccess
{
    public const READABLE = 'readable';

    public const NEEDS_BROWSER = 'needs_browser';

    /** Any other non-2xx: the site is broken or the URL is wrong, not walled. */
    public const HTTP_ERROR = 'http_error';

    /**
     * Confident phrases a page uses when it's gating the real content
     * behind a sign-in — distinct from an empty JS shell, where there's
     * barely any text to search at all.
     */
    private const LOGIN_WALL_PATTERNS = [
        '/sign[\s-]*in\s+to\s+(view|continue|apply|see)/i',
        '/log[\s-]*in\s+to\s+(view|continue|apply|see)/i',
        '/create\s+a\s+(free\s+)?account\s+to\s+(view|continue|apply|see)/i',
        '/you\s+must\s+be\s+(signed|logged)\s+in/i',
        '/please\s+(sign|log)\s+in\s+to/i',
    ];

    /**
     * Bot-protection interstitials (Cloudflare, DataDome, PerimeterX,
     * Akamai…), matched against the raw HTML since most of them render
     * almost no visible text. These block a datacentre server even when
     * a home browser gets straight through.
     */
    private const BOT_CHALLENGE_PATTERNS = [
        '/<title>\s*just a moment\.{0,3}\s*<\/title>/i',
        '/cf-browser-verification|challenge-platform|cf_chl_opt/i',
        '/checking (if the site connection is secure|your browser)/i',
        '/captcha-delivery\.com|datadome/i',
        '/px-captcha|perimeterx/i',
        '/access denied.{0,200}reference\s*#/is',
    ];

    /**
     * Mount points of client-rendered apps (React, Next, Nuxt, Vue…).
     * Empty in the server's HTML means the content only appears once a
     * browser runs the JavaScript.
     */
    private const EMPTY_APP_ROOT_PATTERN = '/<div[^>]+id=["\'](root|app|__next|__nuxt)["\'][^>]*>\s*<\/div>/i';

    /** Below this much visible text, an empty app root means an app shell. */
    private const APP_SHELL_MAX_TEXT = 1000;

    public function __construct(private readonly HtmlTextExtractor $extractor) {}

    /**
     * Order matters: a bot challenge is often served as a 403 or 503, so
     * it's checked before the status code; an ordinary 404/5xx is an
     * error (retry or fix the URL), never a reason to escalate to the
     * browser tier, so it's checked before the content heuristics.
     *
     * `text` is the page's visible text (for callers that keep it, like
     * enrichment); `reason` explains any verdict other than readable.
     *
     * @return array{verdict: self::READABLE|self::NEEDS_BROWSER|self::HTTP_ERROR, reason: ?string, text: string}
     */
    public function assess(Response $response): array
    {
        $body = $response->body();
        $text = $this->extractor->extractText($body);

        [$verdict, $reason] = match (true) {
            $this->isBotChallenge($response, $body) => [self::NEEDS_BROWSER, 'Bot protection challenge (e.g. Cloudflare) — blocks server requests'],
            in_array($response->status(), [401, 403], true) => [self::NEEDS_BROWSER, "HTTP {$response->status()} — login required"],
            $response->failed() => [self::HTTP_ERROR, "HTTP {$response->status()}"],
            $this->looksLikeLoginWall($text) => [self::NEEDS_BROWSER, 'Page content indicates a sign-in wall'],
            mb_strlen($text) < (int) config('magpie.enrichment.min_body_length') => [self::NEEDS_BROWSER, 'Page body too short after stripping markup (likely a JS-rendered shell)'],
            preg_match(self::EMPTY_APP_ROOT_PATTERN, $body) === 1 && mb_strlen($text) < self::APP_SHELL_MAX_TEXT => [self::NEEDS_BROWSER, 'Content is rendered by JavaScript (empty app container)'],
            default => [self::READABLE, null],
        };

        return ['verdict' => $verdict, 'reason' => $reason, 'text' => $text];
    }

    private function isBotChallenge(Response $response, string $body): bool
    {
        if ($response->header('cf-mitigated') === 'challenge') {
            return true;
        }

        // Challenge pages are small; don't scan a full page that merely
        // mentions one of these strings in passing.
        if (strlen($body) > 200_000) {
            return false;
        }

        foreach (self::BOT_CHALLENGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $body) === 1) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeLoginWall(string $text): bool
    {
        foreach (self::LOGIN_WALL_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
