<?php

declare(strict_types=1);

namespace App\Support;

final class UrlCanonicalizer
{
    /**
     * Known tracking query params to strip. Extend as new ones turn up in
     * real listing URLs — this is not meant to be exhaustive on day one.
     */
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'gclsrc', 'fbclid', 'msclkid', 'mc_cid', 'mc_eid',
        'igshid', 'ref', 'ref_src', 'ref_url', '_ga', 'icid', 'trk', 'source',
    ];

    /**
     * Strip tracking params and normalize a listing URL for dedupe.
     *
     * This does not resolve redirects — that requires an actual HTTP
     * fetch, which belongs to the intake drivers that do the fetching
     * (rss/api, email, html), not this pure string transform.
     */
    public static function canonicalize(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'])) {
            return trim($url);
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);

        $port = $parts['port'] ?? null;
        $isDefaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $portSuffix = ($port && ! $isDefaultPort) ? ':'.$port : '';

        $path = $parts['path'] ?? '';
        $path = $path === '' ? '/' : $path;
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = '';
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);

            foreach (array_keys($params) as $key) {
                if (in_array(strtolower($key), self::TRACKING_PARAMS, true)) {
                    unset($params[$key]);
                }
            }

            if (! empty($params)) {
                ksort($params);
                $query = '?'.http_build_query($params);
            }
        }

        return "{$scheme}://{$host}{$portSuffix}{$path}{$query}";
    }
}
