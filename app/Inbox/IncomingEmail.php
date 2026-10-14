<?php

namespace App\Inbox;

class IncomingEmail
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $from,
        public readonly string $subject,
        public readonly ?string $bodyHtml,
        public readonly ?string $bodyText,
        public readonly \DateTimeInterface $receivedAt,
    ) {}
}
