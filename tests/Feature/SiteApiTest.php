<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/admin/sites')->assertUnauthorized();
    $this->postJson('/api/admin/sites', [])->assertUnauthorized();
});

test('a user can list only their own sites', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Site::factory()->for($user)->create(['name' => 'Mine']);
    Site::factory()->for($otherUser)->create(['name' => 'Not mine']);

    $response = $this->actingAs($user)->getJson('/api/admin/sites')->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names->all())->toBe(['Mine']);
});

test('a user can create a new rss site with a known adapter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/admin/sites', [
        'name' => 'We Work Remotely',
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'driver' => 'rss',
        'config' => ['adapter' => 'we_work_remotely'],
        'enabled' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'We Work Remotely')
        ->assertJsonPath('data.driver', 'rss');

    expect(Site::where('user_id', $user->id)->count())->toBe(1);
});

test('creating an rss/api site without a known adapter is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/admin/sites', [
        'name' => 'Mystery Board',
        'url' => 'https://example.com/feed',
        'driver' => 'rss',
        'config' => ['adapter' => 'totally_made_up'],
    ])->assertUnprocessable();
});

test('an html-driver site does not require an adapter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/admin/sites', [
        'name' => 'Some Careers Page',
        'url' => 'https://example.com/careers',
        'driver' => 'html',
        'config' => [],
    ])->assertCreated();
});

test('a user cannot update another user\'s site', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $site = Site::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->putJson("/api/admin/sites/{$site->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('a user cannot delete another user\'s site', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $site = Site::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->deleteJson("/api/admin/sites/{$site->id}")
        ->assertForbidden();

    expect(Site::find($site->id))->not->toBeNull();
});

test('a user can update their own site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create(['enabled' => true]);

    $this->actingAs($user)
        ->putJson("/api/admin/sites/{$site->id}", ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});

test('a user can delete their own site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson("/api/admin/sites/{$site->id}")->assertNoContent();

    expect(Site::find($site->id))->toBeNull();
});

test('a user can manually trigger a run for their own site', function () {
    $body = file_get_contents(base_path('tests/Fixtures/wwr.rss'));
    Http::fake(['weworkremotely.com/*' => Http::response($body, 200)]);

    $user = User::factory()->create();
    $site = Site::factory()->for($user)->create([
        'url' => 'https://weworkremotely.com/categories/remote-programming-jobs.rss',
        'config' => ['adapter' => 'we_work_remotely'],
    ]);

    $this->actingAs($user)
        ->postJson("/api/admin/sites/{$site->id}/run")
        ->assertOk()
        ->assertJsonPath('data.last_status', 'ok');
});

test('a user cannot trigger a run for another user\'s site', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $site = Site::factory()->for($otherUser)->create();

    $this->actingAs($user)->postJson("/api/admin/sites/{$site->id}/run")->assertForbidden();
});
