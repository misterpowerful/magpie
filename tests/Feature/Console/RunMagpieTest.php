<?php

declare(strict_types=1);

use App\Contracts\LlmDriver;
use App\Inbox\MailSource;
use App\Models\Run;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeLlmDriver;
use Tests\Support\FakeMailSource;

beforeEach(function () {
    $this->app->instance(MailSource::class, new FakeMailSource);
    $this->app->instance(LlmDriver::class, new FakeLlmDriver);
});

test('an unknown trigger is rejected and no Run row is created', function () {
    $this->artisan('magpie:run', ['--trigger' => 'bogus'])->assertFailed();

    expect(Run::count())->toBe(0);
});

test('a normal run creates a Run row and exits successfully', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $this->artisan('magpie:run')->assertSuccessful();

    expect(Run::count())->toBe(1);
    expect(Run::first()->trigger)->toBe('schedule');
});

test('a held lock prevents a concurrent run from doing any work', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $lock = Cache::lock('magpie:run-lock', 60);
    $lock->get();

    try {
        $this->artisan('magpie:run')->assertSuccessful();

        expect(Run::count())->toBe(0);
    } finally {
        $lock->release();
    }
});

test('releasing the lock allows the next run to proceed', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $lock = Cache::lock('magpie:run-lock', 60);
    $lock->get();
    $lock->release();

    $this->artisan('magpie:run')->assertSuccessful();

    expect(Run::count())->toBe(1);
});
