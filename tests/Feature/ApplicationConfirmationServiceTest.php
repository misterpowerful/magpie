<?php

declare(strict_types=1);

use App\Contracts\Notifier;
use App\Inbox\ApplicationConfirmationMatcher;
use App\Inbox\IncomingEmail;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\ProcessedConfirmationEmail;
use App\Models\User;
use App\Services\ApplicationConfirmationService;
use App\Services\ListingStatusService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeMailSource;
use Tests\Support\FakeNotifier;

function makeConfirmationEmail(
    string $id,
    string $subject,
    string $body,
    string $from = 'careers@acme.example',
    ?DateTimeImmutable $receivedAt = null,
): IncomingEmail {
    return new IncomingEmail(
        messageId: $id,
        from: $from,
        subject: $subject,
        bodyHtml: null,
        bodyText: $body,
        receivedAt: $receivedAt ?? new DateTimeImmutable('2026-10-01 09:00:00'),
    );
}

function makeConfirmationService(FakeMailSource $mailSource, FakeNotifier $notifier): ApplicationConfirmationService
{
    return new ApplicationConfirmationService(
        $mailSource,
        new ApplicationConfirmationMatcher,
        app(ListingStatusService::class),
        $notifier,
    );
}

beforeEach(function () {
    $this->mailSource = new FakeMailSource;
    $this->notifier = new FakeNotifier;
    $this->app->instance(MailSource::class, $this->mailSource);
    $this->app->instance(Notifier::class, $this->notifier);
});

test('a confirmation email matching exactly one open listing marks it applied', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create([
        'company' => 'Acme Corp',
        'title' => 'Backend Engineer',
        'state' => 'scored',
        'status' => 'new',
    ]);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['matched'])->toBe(1);
    expect($stats['ambiguous'])->toBe(0);
    expect($stats['no_match'])->toBe(0);

    $listing->refresh();
    expect($listing->status)->toBe('applied');
    expect($listing->applied_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00');
    expect($listing->status_reason)->toContain('Thank you for applying to Acme Corp');

    expect($listing->statusHistory()->where('source', 'email')->exists())->toBeTrue();
    expect($this->notifier->notifications)->toBe([]);

    $record = ProcessedConfirmationEmail::where('message_id', 'conf-1')->first();
    expect($record->outcome)->toBe('matched');
    expect($record->listing_id)->toBe($listing->id);
});

test('a confirmation matching two open listings for the same company is reported, not guessed', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'title' => 'Backend Engineer', 'state' => 'scored', 'status' => 'new']);
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'title' => 'Frontend Engineer', 'state' => 'new', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['matched'])->toBe(0);
    expect($stats['ambiguous'])->toBe(1);
    expect(Listing::where('status', 'applied')->count())->toBe(0);

    expect($this->notifier->notifications)->toHaveCount(1);
    expect($this->notifier->notifications[0]['title'])->toContain('ambiguous');

    expect(ProcessedConfirmationEmail::where('message_id', 'conf-1')->first()->outcome)->toBe('ambiguous');
});

test('a confirmation matching no open listing is reported, not guessed', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Globex Industries', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['no_match'])->toBe(1);
    expect($this->notifier->notifications)->toHaveCount(1);
    expect($this->notifier->notifications[0]['title'])->toContain('unmatched');
    expect(ProcessedConfirmationEmail::where('message_id', 'conf-1')->first()->outcome)->toBe('no_match');
});

test('an ordinary (non-confirmation) email is ignored and never recorded', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('newsletter-1', 'Your weekly newsletter', 'Here are this week\'s top stories.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['not_confirmation'])->toBe(1);
    expect($this->notifier->notifications)->toBe([]);
    expect(ProcessedConfirmationEmail::count())->toBe(0);
});

test('an already-seen message is never reprocessed or re-notified', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Globex Industries', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $service->run($user->id);
    expect($this->notifier->notifications)->toHaveCount(1);

    $stats = $service->run($user->id);

    expect($stats['already_seen'])->toBe(1);
    expect($stats['no_match'])->toBe(0);
    // Still just the one notification from the first run.
    expect($this->notifier->notifications)->toHaveCount(1);
});

test('an already-applied listing is never re-matched as a candidate', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'scored', 'status' => 'applied']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['no_match'])->toBe(1);
});

test('a filtered-out listing is never matched as a candidate', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'filtered_out', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $stats = $service->run($user->id);

    expect($stats['no_match'])->toBe(1);
});

test('a second run is skipped cleanly while another confirmation check is already in progress', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $lock = Cache::lock('magpie:confirmation-check-lock', 60);
    $lock->get();

    try {
        $service = makeConfirmationService($this->mailSource, $this->notifier);
        $stats = $service->run($user->id);

        expect($stats['locked'])->toBeTrue();
        expect($stats['scanned'])->toBe(0);
        expect(Listing::where('status', 'applied')->count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('confirmation emails are never marked processed/moved — the owner wants to still see them', function () {
    $user = User::factory()->create();
    Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        makeConfirmationEmail('conf-1', 'Thank you for applying to Acme Corp', 'We have received your application.'),
    ];

    $service = makeConfirmationService($this->mailSource, $this->notifier);
    $service->run($user->id);

    expect($this->mailSource->processedMessageIds)->toBe([]);
});
