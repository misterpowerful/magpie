<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;

/**
 * All intake drivers produce the same listing stub shape consumed by
 * ListingIntakeService::firstOrCreateFromStub (spec section 5: "all
 * intake drivers produce listing stubs").
 */
interface IntakeDriver
{
    /**
     * @return array<int, array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}>
     */
    public function fetch(Site $site): array;
}
