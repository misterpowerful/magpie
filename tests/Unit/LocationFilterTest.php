<?php

declare(strict_types=1);

use App\Filters\FilterResult;
use App\Filters\LocationFilter;

beforeEach(function () {
    $this->filter = new LocationFilter;
});

test('a remote posting with no eligible-states restriction passes cleanly', function () {
    $verdict = $this->filter->check('This is a fully remote position. We value autonomy and clear communication.', 'remote');

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags)->toBe([]);
});

test('a remote posting whose eligible states include Illinois passes', function () {
    $verdict = $this->filter->check(
        'This role is open to candidates in the following states: Illinois, Texas, Colorado.',
        'remote'
    );

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a remote posting whose eligible states exclude Illinois fails', function () {
    $verdict = $this->filter->check(
        'This role is open to candidates in the following states: California, Texas, New York.',
        'remote'
    );

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('a remote posting with an eligible-states clause that cannot be parsed passes with an ambiguous flag', function () {
    $verdict = $this->filter->check(
        'We are authorized to work in select locations across the country.',
        'remote'
    );

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['ambiguous_location'])->toBeTrue();
});

test('an onsite posting based in Illinois passes', function () {
    $verdict = $this->filter->check('This is an on-site role. Location: Chicago, IL.', 'onsite');

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['chicago'] ?? false)->toBeTrue();
});

test('an onsite posting based outside Illinois fails', function () {
    $verdict = $this->filter->check('This is an on-site role. Location: Austin, TX.', 'onsite');

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('a hybrid posting based outside Illinois fails', function () {
    $verdict = $this->filter->check('This hybrid role is based in Denver, CO.', 'hybrid');

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('an onsite posting with no location stated passes with an ambiguous flag', function () {
    $verdict = $this->filter->check('Join our growing engineering team and make an impact.', 'onsite');

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['ambiguous_location'])->toBeTrue();
});

test('an unknown/unspecified remote_type is treated as ambiguous and passes with a flag', function () {
    $verdict = $this->filter->check('Join our team!', null);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['ambiguous_location'])->toBeTrue();
});

test('a lowercase "in" or "or" never gets mistaken for a state abbreviation', function () {
    $verdict = $this->filter->check(
        'This is an on-site role. Location: based in our main office or satellite office.',
        'onsite'
    );

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['ambiguous_location'])->toBeTrue();
});

test('a multi-state onsite clause naming Illinois among others is ambiguous, not a confident pass', function () {
    $verdict = $this->filter->check(
        'This is an on-site role. Location: offices in Illinois and California.',
        'onsite'
    );

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['ambiguous_location'])->toBeTrue();
});
