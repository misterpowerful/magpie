<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;
use App\Support\Dom;
use App\Support\RemoteTypeGuesser;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Spec section 5 "html": server-side fetch and parse for plain listing
 * pages and company career pages that have no feed/API. Unlike the
 * rss/api adapters (one PHP class per source, because each has its own
 * JSON/XML shape), this single class serves every html site — the
 * per-site part is XPath selectors in site.config.selectors, since a
 * career page's structure can't be known in advance.
 *
 * This driver only produces stubs (url/title/company/location); the
 * full body comes from the generic enrichment stage afterward, same as
 * every other intake driver.
 *
 * Expected site.config shape:
 *   {
 *     "adapter": "html",
 *     "selectors": {
 *       "list_item": "//div[@class='job']",   // XPath, one match per listing
 *       "link": ".//a/@href",                  // relative to list_item; required
 *       "title": ".//h2",                      // relative to list_item; required
 *       "company": ".//span[@class='company']",// relative to list_item; optional
 *       "location": ".//span[@class='location']" // relative to list_item; optional
 *     }
 *   }
 */
final class HtmlIntakeDriver implements IntakeDriver
{
    public function headers(): array
    {
        return [];
    }

    public function parse(Site $site, Response $response): array
    {
        $selectors = $this->validateSelectors($site);
        $linkExpr = $selectors['link'];
        $titleExpr = $selectors['title'];

        $xpath = $this->buildXPath($response->body());
        $items = $xpath->query($selectors['list_item']);

        if ($items === false) {
            throw new RuntimeException("Site \"{$site->name}\" has an invalid selectors.list_item XPath expression");
        }

        $stubs = [];

        foreach ($items as $item) {
            $href = $this->queryOne($xpath, $linkExpr, $item);
            $url = $this->resolveUrl($site->url, $href);
            $title = $this->queryOne($xpath, $titleExpr, $item);

            if ($url === null || $title === null) {
                continue;
            }

            $locationText = $this->queryOne($xpath, $selectors['location'] ?? null, $item);

            $stubs[] = [
                'url' => $url,
                'company' => $this->queryOne($xpath, $selectors['company'] ?? null, $item),
                'title' => $title,
                'location_text' => $locationText,
                'remote_type' => RemoteTypeGuesser::guess($locationText),
                'raw_text' => null,
            ];
        }

        return $stubs;
    }

    /**
     * @return array{list_item: string, link: string, title: string, company?: string, location?: string}
     */
    private function validateSelectors(Site $site): array
    {
        $selectors = $site->config['selectors'] ?? [];
        $listItemExpr = $selectors['list_item'] ?? null;
        $linkExpr = $selectors['link'] ?? null;
        $titleExpr = $selectors['title'] ?? null;

        if ($listItemExpr === null || $linkExpr === null || $titleExpr === null) {
            throw new RuntimeException("Site \"{$site->name}\" is missing required selectors (list_item, link, title)");
        }

        return $selectors;
    }

    private function buildXPath(string $html): DOMXPath
    {
        return new DOMXPath(Dom::fromHtml($html));
    }

    private function queryOne(DOMXPath $xpath, ?string $expr, DOMNode $context): ?string
    {
        if ($expr === null) {
            return null;
        }

        $nodes = $xpath->query($expr, $context);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        $value = $node instanceof \DOMAttr ? $node->value : trim((string) $node?->textContent);

        return $value !== '' ? $value : null;
    }

    private function resolveUrl(string $baseUrl, ?string $href): ?string
    {
        if ($href === null || $href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (str_starts_with($href, '//')) {
            return "{$scheme}:{$href}";
        }

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$href}";
        }

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');

        return "{$scheme}://{$host}{$basePath}/{$href}";
    }
}
