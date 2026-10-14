<?php

use App\Inbox\EmailParser;
use App\Inbox\EmailParserRegistry;
use App\Inbox\IncomingEmail;

beforeEach(function () {
    $this->email = new IncomingEmail(
        messageId: 'msg-1',
        from: 'sender@example.com',
        subject: 'Test',
        bodyHtml: null,
        bodyText: 'body',
        receivedAt: new DateTimeImmutable,
    );
});

test('resolves the first parser that claims the email', function () {
    $registry = new EmailParserRegistry;

    $never = new class implements EmailParser
    {
        public function canParse(IncomingEmail $email): bool
        {
            return false;
        }

        public function parse(IncomingEmail $email): array
        {
            return [];
        }
    };

    $always = new class implements EmailParser
    {
        public function canParse(IncomingEmail $email): bool
        {
            return true;
        }

        public function parse(IncomingEmail $email): array
        {
            return [];
        }
    };

    $registry->register($never)->register($always);

    expect($registry->resolve($this->email))->toBe($always);
});

test('returns null when no parser (not even a fallback) is registered', function () {
    $registry = new EmailParserRegistry;

    expect($registry->resolve($this->email))->toBeNull();
});
