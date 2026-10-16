<?php

declare(strict_types=1);

use App\Contracts\LlmDriver;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\Preference;
use App\Models\Run;
use App\Models\User;
use App\Services\RunRequestService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeLlmDriver;
use Tests\Support\FakeMailSource;

beforeEach(function () {
    Cache::flush();
    $this->app->instance(MailSource::class, new FakeMailSource);
    $this->app->instance(LlmDriver::class, new FakeLlmDriver('Strong'));
});

test('unauthenticated requests are rejected', function () {
    $this->postJson('/api/admin/runs')->assertUnauthorized();
});

test('requesting a run files a pending request without starting a run', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/runs')
        ->assertStatus(202)
        ->assertJsonPath('data.pending', true)
        ->assertJsonPath('data.running', false);

    expect(Run::count())->toBe(0);
});

test('the runs index reports whether a request is pending or a run is in progress', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/runs')
        ->assertJsonPath('pending', false)
        ->assertJsonPath('running', false);

    $this->actingAs($user)->postJson('/api/admin/runs');
    $this->actingAs($user)->getJson('/api/runs')->assertJsonPath('pending', true);

    Run::factory()->create(['status' => 'running', 'started_at' => now()]);
    $this->actingAs($user)->getJson('/api/runs')->assertJsonPath('running', true);
});

test('a run row stuck at running past the lock TTL no longer counts as running', function () {
    Run::factory()->create([
        'status' => 'running',
        'started_at' => now()->subSeconds((int) config('magpie.run.lock_seconds') + 60),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/runs')
        ->assertJsonPath('running', false);
});

test('requesting while a run is already in progress files nothing new', function () {
    Run::factory()->create(['status' => 'running', 'started_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/admin/runs')
        ->assertStatus(202)
        ->assertJsonPath('data.pending', false)
        ->assertJsonPath('data.running', true);
});

test('the scheduled command does nothing when no run was requested', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $this->artisan('magpie:run-requested')->assertSuccessful();

    expect(Run::count())->toBe(0);
});

test('the scheduled command runs the full pipeline as a manual run and clears the request', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);
    Preference::factory()->create(['user_id' => $user->id]);
    Listing::factory()->for($user)->create(['state' => 'filtered_in']);

    $this->actingAs($user)->postJson('/api/admin/runs')->assertStatus(202);

    $this->artisan('magpie:run-requested')->assertSuccessful();

    $run = Run::sole();
    expect($run->trigger)->toBe('manual');
    expect($run->status)->toBe('done');
    expect($run->stage_stats)->toHaveKeys(['intake', 'stub_filter', 'enrichment', 'hard_filter', 'scoring']);
    expect(Listing::where('state', 'scored')->count())->toBe(1);

    $this->actingAs($user)->getJson('/api/runs')
        ->assertJsonPath('pending', false)
        ->assertJsonPath('running', false)
        ->assertJsonPath('data.0.trigger', 'manual');

    $this->artisan('magpie:run-requested')->assertSuccessful();
    expect(Run::count())->toBe(1);
});

test('a requested run is dropped, not queued, when another run holds the lock', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);
    $lock = Cache::lock('magpie:run-lock', 60);
    $lock->get();

    $this->actingAs(User::first())->postJson('/api/admin/runs');
    $this->artisan('magpie:run-requested')->assertSuccessful();

    expect(Run::count())->toBe(0);
    expect(app(RunRequestService::class)->isPending())->toBeFalse();

    $lock->release();
});
