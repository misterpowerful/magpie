<?php

declare(strict_types=1);

use App\Inbox\EmailParser;
use App\Inbox\EmailParserRegistry;
use App\Inbox\IncomingEmail;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeMailSource;

beforeEach(fn () => Cache::flush());

function bindAlertParser(): void
{
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

    app()->instance(EmailParserRegistry::class, (new EmailParserRegistry)->register($parser));
}

function alertEmail(): IncomingEmail
{
    return new IncomingEmail(
        messageId: 'msg-1',
        from: 'alerts@example.com',
        subject: 'Job alert',
        bodyHtml: null,
        bodyText: 'body',
        receivedAt: new DateTimeImmutable,
    );
}

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/admin/email-intake/run')->assertUnauthorized();
    $this->getJson('/api/admin/email-intake/status')->assertUnauthorized();
});

test('status is idle until a pull is requested', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/email-intake/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'idle');
});

test('requesting a pull files a pending request without fetching any mail', function () {
    $mailSource = new FakeMailSource;
    $mailSource->emails = [alertEmail()];
    $this->app->instance(MailSource::class, $mailSource);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/email-intake/run')
        ->assertStatus(202)
        ->assertJsonPath('data.state', 'pending');

    expect($mailSource->processedMessageIds)->toBe([]);
    expect(Listing::count())->toBe(0);
});

test('requesting again while a pull is pending keeps the original request', function () {
    $user = User::factory()->create();

    $first = $this->actingAs($user)->postJson('/api/admin/email-intake/run')->json('data.requested_at');
    $this->travel(30)->seconds();
    $second = $this->actingAs($user)->postJson('/api/admin/email-intake/run')->json('data.requested_at');

    expect($second)->toBe($first);
});

test('the scheduled command does nothing when no pull was requested', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);
    $mailSource = new FakeMailSource;
    $mailSource->emails = [alertEmail()];
    $this->app->instance(MailSource::class, $mailSource);

    $this->artisan('magpie:run-requested-email-pull')->assertSuccessful();

    expect($mailSource->processedMessageIds)->toBe([]);
    expect(Cache::get('magpie:email-pull'))->toBeNull();
});

test('the scheduled command runs a requested pull and records the result', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);

    $mailSource = new FakeMailSource;
    $mailSource->emails = [alertEmail()];
    $this->app->instance(MailSource::class, $mailSource);
    bindAlertParser();

    $this->actingAs($user)->postJson('/api/admin/email-intake/run')->assertStatus(202);

    $this->artisan('magpie:run-requested-email-pull')->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/email-intake/status')
        ->assertOk()
        ->assertJsonPath('data.state', 'done')
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.stubs', 1)
        ->assertJsonPath('data.failed', 0);

    expect(Listing::where('user_id', $user->id)->count())->toBe(1);
    expect($mailSource->processedMessageIds)->toBe(['msg-1']);
});

test('a pull that blows up is recorded as failed and the request is cleared', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);

    $this->app->instance(MailSource::class, new class extends FakeMailSource
    {
        public function fetchUnprocessed(): array
        {
            throw new RuntimeException('imap down');
        }
    });

    $this->actingAs($user)->postJson('/api/admin/email-intake/run')->assertStatus(202);
    $this->artisan('magpie:run-requested-email-pull')->assertSuccessful();

    $this->actingAs($user)
        ->getJson('/api/admin/email-intake/status')
        ->assertJsonPath('data.state', 'failed');

    $this->actingAs($user)
        ->postJson('/api/admin/email-intake/run')
        ->assertStatus(202)
        ->assertJsonPath('data.state', 'pending');
});
