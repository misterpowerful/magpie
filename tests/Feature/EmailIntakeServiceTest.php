<?php

declare(strict_types=1);

use App\Inbox\EmailParser;
use App\Inbox\EmailParserRegistry;
use App\Inbox\IncomingEmail;
use App\Models\Listing;
use App\Models\User;
use App\Services\EmailIntakeService;
use App\Services\ListingIntakeService;
use Tests\Support\FakeMailSource;

function makeAlert(string $id, string $subject = 'Job alert'): IncomingEmail
{
    return new IncomingEmail(
        messageId: $id,
        from: 'alerts@example.com',
        subject: $subject,
        bodyHtml: null,
        bodyText: 'body',
        receivedAt: new DateTimeImmutable,
    );
}

test('processed emails create listings and get relabeled', function () {
    $user = User::factory()->create();
    $mailSource = new FakeMailSource;
    $mailSource->emails = [makeAlert('msg-1')];

    $parser = new class implements EmailParser
    {
        public function canParse(IncomingEmail $email): bool
        {
            return true;
        }

        public function parse(IncomingEmail $email): array
        {
            return [[
                'url' => 'https://example.com/job/1',
                'company' => 'Acme',
                'title' => 'Backend Engineer',
                'location_text' => null,
                'remote_type' => 'remote',
                'raw_text' => null,
            ]];
        }
    };

    $registry = (new EmailParserRegistry)->register($parser);
    $service = new EmailIntakeService($mailSource, $registry, app(ListingIntakeService::class));

    $result = $service->run($user->id);

    expect($result)->toBe(['processed' => 1, 'stubs' => 1, 'failed' => 0]);
    expect(Listing::where('user_id', $user->id)->count())->toBe(1);
    expect($mailSource->processedMessageIds)->toBe(['msg-1']);
});

test('a parser that throws leaves the email unprocessed for retry next run', function () {
    $user = User::factory()->create();
    $mailSource = new FakeMailSource;
    $mailSource->emails = [makeAlert('msg-1')];

    $parser = new class implements EmailParser
    {
        public function canParse(IncomingEmail $email): bool
        {
            return true;
        }

        public function parse(IncomingEmail $email): array
        {
            throw new RuntimeException('boom');
        }
    };

    $registry = (new EmailParserRegistry)->register($parser);
    $service = new EmailIntakeService($mailSource, $registry, app(ListingIntakeService::class));

    $result = $service->run($user->id);

    expect($result)->toBe(['processed' => 0, 'stubs' => 0, 'failed' => 1]);
    expect($mailSource->processedMessageIds)->toBe([]);
});

test('an email no parser claims is counted as failed, not processed', function () {
    $user = User::factory()->create();
    $mailSource = new FakeMailSource;
    $mailSource->emails = [makeAlert('msg-1')];

    $registry = new EmailParserRegistry; // empty — nothing claims anything
    $service = new EmailIntakeService($mailSource, $registry, app(ListingIntakeService::class));

    $result = $service->run($user->id);

    expect($result)->toBe(['processed' => 0, 'stubs' => 0, 'failed' => 1]);
});

test('one email yielding two jobs still dedupes correctly against an existing listing', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create([
        'canonical_url' => 'https://example.com/job/1',
        'company' => 'Acme',
        'title' => 'Backend Engineer',
        'content_hash' => hash('sha256', 'acme|backend engineer'),
    ]);

    $mailSource = new FakeMailSource;
    $mailSource->emails = [makeAlert('msg-1')];

    $parser = new class implements EmailParser
    {
        public function canParse(IncomingEmail $email): bool
        {
            return true;
        }

        public function parse(IncomingEmail $email): array
        {
            return [
                ['url' => 'https://example.com/job/1', 'company' => 'Acme', 'title' => 'Backend Engineer', 'location_text' => null, 'remote_type' => null, 'raw_text' => null],
                ['url' => 'https://example.com/job/2', 'company' => 'Globex', 'title' => 'Staff Engineer', 'location_text' => null, 'remote_type' => null, 'raw_text' => null],
            ];
        }
    };

    $registry = (new EmailParserRegistry)->register($parser);
    $service = new EmailIntakeService($mailSource, $registry, app(ListingIntakeService::class));

    $service->run($user->id);

    expect(Listing::where('user_id', $user->id)->count())->toBe(2);
});
