<?php

declare(strict_types=1);

namespace App\Inbox;

final readonly class IncomingEmail
{
    public function __construct(
        public string $messageId,
        public string $from,
        public string $subject,
        public ?string $bodyHtml,
        public ?string $bodyText,
        public \DateTimeInterface $receivedAt,
    ) {}
}
