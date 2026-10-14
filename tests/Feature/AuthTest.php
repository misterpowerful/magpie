<?php

declare(strict_types=1);

use App\Models\User;

test('unauthenticated request to a protected api route is rejected', function () {
    $this->getJson('/api/ping')->assertUnauthorized();
});

test('unauthenticated non-JSON request to a protected api route gets a clean 401 instead of erroring', function () {
    $this->get('/api/ping')->assertUnauthorized();
});

test('user can log in with correct credentials and reach a protected api route', function () {
    $user = User::factory()->create();

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->getJson('/api/ping')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('login is rejected with the wrong password', function () {
    $user = User::factory()->create();

    $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertUnprocessable();

    $this->getJson('/api/ping')->assertUnauthorized();
});
