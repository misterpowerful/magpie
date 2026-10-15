<?php

declare(strict_types=1);

use App\Contracts\Notifier;
use App\Inbox\IncomingEmail;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeMailSource;
use Tests\Support\FakeNotifier;

beforeEach(function () {
    $this->mailSource = new FakeMailSource;
    $this->app->instance(MailSource::class, $this->mailSource);
    $this->app->instance(Notifier::class, new FakeNotifier);
});

test('the command marks a matching listing applied', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);
    $listing = Listing::factory()->for($user)->create(['company' => 'Acme Corp', 'state' => 'scored', 'status' => 'new']);

    $this->mailSource->confirmationEmails = [
        new IncomingEmail(
            messageId: 'conf-1',
            from: 'careers@acme.example',
            subject: 'Thank you for applying to Acme Corp',
            bodyHtml: null,
            bodyText: 'We have received your application.',
            receivedAt: new DateTimeImmutable,
        ),
    ];

    $this->artisan('magpie:check-confirmations')->assertSuccessful();

    expect($listing->refresh()->status)->toBe('applied');
});

test('the command fails cleanly when there is no admin user', function () {
    $this->artisan('magpie:check-confirmations')->assertFailed();
});

test('the command skips without error when the confirmation lock is already held', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $lock = Cache::lock('magpie:confirmation-check-lock', 60);
    $lock->get();

    try {
        $this->artisan('magpie:check-confirmations')->assertSuccessful();
    } finally {
        $lock->release();
    }
});
