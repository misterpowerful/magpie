<?php

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
}
