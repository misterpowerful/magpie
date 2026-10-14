<?php

declare(strict_types=1);

use App\Notifiers\PushoverNotifier;
use Illuminate\Support\Facades\Http;

test('a configured notifier posts to the Pushover API with the right payload', function () {
    config(['services.pushover.token' => 'app-token', 'services.pushover.user_key' => 'user-key']);
    Http::fake(['api.pushover.net/*' => Http::response(['status' => 1], 200)]);

    (new PushoverNotifier)->notify('Magpie: new Strong match', '2 new Strong-rated listings from this run.');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.pushover.net/1/messages.json'
            && $request['token'] === 'app-token'
            && $request['user'] === 'user-key'
            && $request['title'] === 'Magpie: new Strong match'
            && $request['message'] === '2 new Strong-rated listings from this run.';
    });
});

test('an unconfigured notifier skips silently without making a request', function () {
    config(['services.pushover.token' => null, 'services.pushover.user_key' => null]);
    Http::fake();

    (new PushoverNotifier)->notify('title', 'message');

    Http::assertNothingSent();
});

test('a failed Pushover request is logged, not thrown', function () {
    config(['services.pushover.token' => 'app-token', 'services.pushover.user_key' => 'user-key']);
    Http::fake(['api.pushover.net/*' => Http::response(['status' => 0, 'errors' => ['invalid token']], 400)]);

    (new PushoverNotifier)->notify('title', 'message');
})->throwsNoExceptions();
