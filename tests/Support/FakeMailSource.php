<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Inbox\IncomingEmail;
use App\Inbox\MailSource;

class FakeMailSource implements MailSource
{
    /** @var array<int, IncomingEmail> */
    public array $emails = [];

    /** @var array<int, string> */
    public array $processedMessageIds = [];

    public function fetchUnprocessed(): array
    {
        return $this->emails;
    }

    public function markProcessed(IncomingEmail $email): void
    {
        $this->processedMessageIds[] = $email->messageId;
    }
}
