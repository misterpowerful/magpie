<?php

declare(strict_types=1);

namespace App\Services;

use App\Intake\IntakeDriverRegistry;
use App\Support\Dom;
use App\Support\PageAccess;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The Add Site form's "Check URL": one fetch of the URL from this
 * server, and a suggestion for which driver can read it — a feed or
 * API (rss/api), a page the server can read directly (html), or a page
 * that needs a real browser (browser, the Claude in Chrome tier).
 *
 * It's a snapshot from this server's network, which is the one that
 * matters (it's the one that does the fetching), but a site can still
 * change later — html site runs re-check via PageAccess.
 */
final class SiteProbeService
{
    public function __construct(private readonly PageAccess $pageAccess) {}

    /**
     * @return array{verdict: 'feed'|'api'|'readable'|'needs_browser'|'unreachable', suggested_driver: ?string, suggested_adapter: ?string, reason: string, feed_url: ?string, http_status: ?int}
     */
    public function probe(string $url): array
    {
        try {
            $response = Http::timeout((int) config('magpie.sites.probe_timeout'))
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.5'])
                ->get($url);
        } catch (ConnectionException $e) {
            return $this->result('unreachable', null, "Couldn't connect: {$e->getMessage()}");
        }

        $known = IntakeDriverRegistry::forUrl($url);

        if ($response->successful() && $this->isFeed($response)) {
            return $this->result('feed', $known['driver'] ?? 'rss', $known
                ? 'RSS/Atom feed with a built-in adapter.'
                : 'RSS/Atom feed, but there is no adapter for this source yet — one needs adding in code.', $known['adapter'] ?? null, status: $response->status());
        }

        if ($response->successful() && $this->isJson($response)) {
            return $this->result('api', $known['driver'] ?? 'api', $known
                ? 'JSON API with a built-in adapter.'
                : 'JSON API, but there is no adapter for this source yet — one needs adding in code.', $known['adapter'] ?? null, status: $response->status());
        }

        $access = $this->pageAccess->assess($response);

        return match ($access['verdict']) {
            PageAccess::NEEDS_BROWSER => $this->result('needs_browser', 'browser', $access['reason'].'. Needs Claude in Chrome.', status: $response->status()),
            PageAccess::HTTP_ERROR => $this->result('unreachable', null, "The server answered {$access['reason']} — check the URL.", status: $response->status()),
            default => $this->readable($url, $response, $known),
        };
    }

    /**
     * @param  array{driver: string, adapter: string}|null  $known
     */
    private function readable(string $url, Response $response, ?array $known): array
    {
        $feedUrl = $this->advertisedFeed($url, $response->body());

        $reason = 'This server can read the page directly. Add XPath selectors for the job list to the site config.';

        if ($known !== null) {
            $reason .= ' This source has a built-in '.$known['driver'].' adapter, though — point the site at its feed/API URL instead.';
        } elseif ($feedUrl !== null) {
            $reason .= ' The page also advertises a feed — that is usually more reliable than selectors.';
        }

        return $this->result('readable', 'html', $reason, feedUrl: $feedUrl, status: $response->status());
    }

    private function isFeed(Response $response): bool
    {
        $type = strtolower((string) $response->header('Content-Type'));

        if (str_contains($type, 'rss') || str_contains($type, 'atom')) {
            return true;
        }

        $head = ltrim(substr($response->body(), 0, 2000));

        return (str_contains($type, 'xml') || str_starts_with($head, '<?xml'))
            && preg_match('/<(rss|feed)[\s>]/i', $head) === 1;
    }

    private function isJson(Response $response): bool
    {
        return str_contains(strtolower((string) $response->header('Content-Type')), 'json') && is_array($response->json());
    }

    /**
     * A `<link rel="alternate" type="application/rss+xml">` in the page
     * head, resolved against the page URL.
     */
    private function advertisedFeed(string $pageUrl, string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        $dom = Dom::fromHtml($html);

        $nodes = (new DOMXPath($dom))->query("//link[@rel='alternate'][contains(@type, 'rss') or contains(@type, 'atom')]/@href");
        $href = $nodes !== false && $nodes->length > 0 ? trim((string) $nodes->item(0)?->nodeValue) : '';

        if ($href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($pageUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return str_starts_with($href, '/') ? $origin.$href : rtrim($pageUrl, '/').'/'.$href;
    }

    /**
     * @return array{verdict: 'feed'|'api'|'readable'|'needs_browser'|'unreachable', suggested_driver: ?string, suggested_adapter: ?string, reason: string, feed_url: ?string, http_status: ?int}
     */
    private function result(string $verdict, ?string $driver, string $reason, ?string $adapter = null, ?string $feedUrl = null, ?int $status = null): array
    {
        return [
            'verdict' => $verdict,
            'suggested_driver' => $driver,
            'suggested_adapter' => $adapter,
            'reason' => $reason,
            'feed_url' => $feedUrl,
            'http_status' => $status,
        ];
    }
}
