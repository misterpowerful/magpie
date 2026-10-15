<?php

declare(strict_types=1);

namespace App\Inbox;

/**
 * Spec section 2: "MailSource interface. IMAP with an app password
 * first." Swappable so a Gmail API/OAuth driver could replace it later
 * without touching the email intake pipeline (only needed if the app
 * is ever marketed — spec section 5).
 */
interface MailSource
{
    /**
     * @return array<int, IncomingEmail>
     */
    public function fetchUnprocessed(): array;

    public function markProcessed(IncomingEmail $email): void;

    /**
     * Spec section 9's "deterministic confirmation matcher": a read-only
     * scan of the owner's normal mail for application-confirmation
     * emails, separate from the job-alert inbox_label above. Must never
     * mark a message read or move it — the owner wants to still see
     * these himself.
     *
     * @return array<int, IncomingEmail>
     */
    public function fetchForConfirmationScan(\DateTimeInterface $since): array;
}
