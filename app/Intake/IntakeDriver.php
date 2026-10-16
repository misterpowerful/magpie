<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;
use Illuminate\Http\Client\Response;

/**
 * All intake drivers produce the same listing stub shape consumed by
 * ListingIntakeService::firstOrCreateFromStub (spec section 5: "all
 * intake drivers produce listing stubs").
 *
 * Drivers only parse: SiteIntakeService makes every request itself
 * (pooled via Http::pool() for a run, singly for "Run now"), so the
 * fetch, its timeout, and the html page-access check live in one place.
 */
interface IntakeDriver
{
    /**
     * @return array<int, array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}>
     */
    public function parse(Site $site, Response $response): array;

    /**
     * Extra headers this source needs on its request (e.g. a User-Agent
     * some sources require), sent by SiteIntakeService's fetch.
     *
     * @return array<string, string>
     */
    public function headers(): array;
}
