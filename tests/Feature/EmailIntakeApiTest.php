<?php

declare(strict_types=1);

use App\Inbox\EmailParser;
use App\Inbox\EmailParserRegistry;
use App\Inbox\IncomingEmail;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\User;
use Tests\Support\FakeMailSource;

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/admin/email-intake/run')->assertUnauthorized();
});

test('a user can manually trigger an email intake pull', function () {
    $user = User::factory()->create();
    $this->app->instance(MailSource::class, new FakeMailSource);

    $this->actingAs($user)
        ->postJson('/api/admin/email-intake/run')
        ->assertOk()
        ->assertJsonPath('data.processed', 0)
        ->assertJsonPath('data.stubs', 0)
        ->assertJsonPath('data.failed', 0);
});

test('a pull creates listings from parsed alert emails', function () {
    $user = User::factory()->create();

    $mailSource = new FakeMailSource;
    $mailSource->emails = [new IncomingEmail(
        messageId: 'msg-1',
        from: 'alerts@example.com',
        subject: 'Job alert',
        bodyHtml: null,
        bodyText: 'body',
        receivedAt: new DateTimeImmutable,
    )];
    $this->app->instance(MailSource::class, $mailSource);

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
    $this->app->instance(EmailParserRegistry::class, (new EmailParserRegistry)->register($parser));

    $this->actingAs($user)
        ->postJson('/api/admin/email-intake/run')
        ->assertOk()
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.stubs', 1);

    expect(Listing::where('user_id', $user->id)->count())->toBe(1);
    expect($mailSource->processedMessageIds)->toBe(['msg-1']);
});
