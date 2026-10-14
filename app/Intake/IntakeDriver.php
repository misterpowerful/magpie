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
 * fetch() and parse() are split so the run orchestrator can fetch every
 * enabled site concurrently via Http::pool() (spec section 8 "per-site
 * stage timeout", and the decision to pool site fetches) while still
 * reusing each driver's own parsing logic; fetch() is defined in terms
 * of parse() for the single-site case (e.g. the admin "run now" button).
 */
interface IntakeDriver
{
    /**
     * @return array<int, array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}>
     */
    public function fetch(Site $site): array;

    /**
     * @return array<int, array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}>
     */
    public function parse(Site $site, Response $response): array;

    /**
     * Extra headers this source needs on its request (e.g. a User-Agent
     * some sources require) — used both by fetch() and by the pooled
     * batch fetch so the two stay in sync.
     *
     * @return array<string, string>
     */
    public function headers(): array;
}
