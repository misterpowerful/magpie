<?php

declare(strict_types=1);

namespace App\Inbox;

/**
 * Spec section 5 "email": "One parser per alert sender (regex/DOM)."
 * Every parser produces the same stub shape consumed by
 * ListingIntakeService::firstOrCreateFromStub — a single alert email
 * (a digest) can contain more than one job, hence the array return.
 */
interface EmailParser
{
    public function canParse(IncomingEmail $email): bool;

    /**
     * @return array<int, array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}>
     */
    public function parse(IncomingEmail $email): array;
}
