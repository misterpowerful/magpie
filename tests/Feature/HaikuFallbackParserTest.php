<?php

use App\Inbox\HaikuFallbackParser;
use App\Inbox\IncomingEmail;
use Tests\Support\FakeLlmDriver;

function makeTestEmail(?string $bodyText = 'body', ?string $bodyHtml = null): IncomingEmail
{
    return new IncomingEmail(
        messageId: 'msg-1',
        from: 'alerts@somejobsboard.example.com',
        subject: 'New jobs for you',
        bodyHtml: $bodyHtml,
        bodyText: $bodyText,
        receivedAt: new DateTimeImmutable,
    );
}

test('always claims the email (it is the catch-all)', function () {
    $parser = new HaikuFallbackParser(new FakeLlmDriver);

    expect($parser->canParse(makeTestEmail()))->toBeTrue();
});

test('parses a JSON array of jobs from the model response', function () {
    $driver = new FakeLlmDriver;
    $driver->completeResponse = json_encode([
        ['url' => 'https://example.com/job/1', 'company' => 'Acme', 'title' => 'Backend Engineer', 'location_text' => 'Remote', 'remote_type' => 'remote'],
        ['url' => 'https://example.com/job/2', 'company' => 'Globex', 'title' => 'Staff Engineer', 'location_text' => null, 'remote_type' => null],
    ]);

    $stubs = (new HaikuFallbackParser($driver))->parse(makeTestEmail());

    expect($stubs)->toHaveCount(2);
    expect($stubs[0]['company'])->toBe('Acme');
    expect($stubs[0]['raw_text'])->toBeNull();
    expect($driver->completeCalls)->toBe(1);
});

test('skips malformed job entries missing a url or title', function () {
    $driver = new FakeLlmDriver;
    $driver->completeResponse = json_encode([
        ['url' => 'https://example.com/job/1', 'title' => 'Backend Engineer'],
        ['company' => 'Globex', 'title' => 'Missing URL'],
        ['url' => 'https://example.com/job/3'],
    ]);

    $stubs = (new HaikuFallbackParser($driver))->parse(makeTestEmail());

    expect($stubs)->toHaveCount(1);
});

test('an empty-array response yields no stubs', function () {
    $driver = new FakeLlmDriver;
    $driver->completeResponse = '[]';

    $stubs = (new HaikuFallbackParser($driver))->parse(makeTestEmail());

    expect($stubs)->toBe([]);
});

test('a non-JSON response is handled gracefully, not an exception', function () {
    $driver = new FakeLlmDriver;
    $driver->completeResponse = 'not json at all';

    $stubs = (new HaikuFallbackParser($driver))->parse(makeTestEmail());

    expect($stubs)->toBe([]);
});

test('an email with no body at all is not sent to the model', function () {
    $driver = new FakeLlmDriver;

    $stubs = (new HaikuFallbackParser($driver))->parse(makeTestEmail(bodyText: '', bodyHtml: null));

    expect($stubs)->toBe([]);
    expect($driver->completeCalls)->toBe(0);
});

test('falls back to the stripped HTML body when there is no plain text body', function () {
    $driver = new FakeLlmDriver;
    $driver->completeResponse = '[]';

    (new HaikuFallbackParser($driver))->parse(makeTestEmail(bodyText: null, bodyHtml: '<p>Job at <b>Acme</b></p>'));

    expect($driver->completePrompts[0])->toContain('Job at Acme');
    expect($driver->completePrompts[0])->not->toContain('<p>');
});
