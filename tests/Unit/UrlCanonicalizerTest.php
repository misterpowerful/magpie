<?php

use App\Support\UrlCanonicalizer;

test('strips known tracking params', function () {
    $url = 'https://example.com/jobs/123?utm_source=indeed&utm_medium=email&ref=abc&foo=bar';

    expect(UrlCanonicalizer::canonicalize($url))->toBe('https://example.com/jobs/123?foo=bar');
});

test('sorts remaining query params for stable ordering', function () {
    $a = UrlCanonicalizer::canonicalize('https://example.com/jobs/1?b=2&a=1');
    $b = UrlCanonicalizer::canonicalize('https://example.com/jobs/1?a=1&b=2');

    expect($a)->toBe($b);
});

test('lowercases the host but not the path', function () {
    expect(UrlCanonicalizer::canonicalize('https://Example.COM/Jobs/ABC'))
        ->toBe('https://example.com/Jobs/ABC');
});

test('strips default ports', function () {
    expect(UrlCanonicalizer::canonicalize('https://example.com:443/jobs/1'))
        ->toBe('https://example.com/jobs/1');
});

test('keeps a non-default port', function () {
    expect(UrlCanonicalizer::canonicalize('https://example.com:8443/jobs/1'))
        ->toBe('https://example.com:8443/jobs/1');
});

test('strips trailing slash except on bare root', function () {
    expect(UrlCanonicalizer::canonicalize('https://example.com/jobs/1/'))->toBe('https://example.com/jobs/1');
    expect(UrlCanonicalizer::canonicalize('https://example.com/'))->toBe('https://example.com/');
    expect(UrlCanonicalizer::canonicalize('https://example.com'))->toBe('https://example.com/');
});

test('drops the fragment entirely', function () {
    expect(UrlCanonicalizer::canonicalize('https://example.com/jobs/1#apply'))
        ->toBe('https://example.com/jobs/1');
});

test('is idempotent on an already-canonical URL', function () {
    $once = UrlCanonicalizer::canonicalize('https://example.com/jobs/1?foo=bar');
    $twice = UrlCanonicalizer::canonicalize($once);

    expect($twice)->toBe($once);
});

test('a URL with only tracking params ends up with no query string', function () {
    expect(UrlCanonicalizer::canonicalize('https://example.com/jobs/1?utm_source=indeed&fbclid=xyz'))
        ->toBe('https://example.com/jobs/1');
});
