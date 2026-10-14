<?php

declare(strict_types=1);

use App\Filters\FilterResult;
use App\Filters\StackFilter;

beforeEach(function () {
    $this->filter = new StackFilter;
});

test('a plain PHP posting passes', function () {
    $verdict = $this->filter->check('We are looking for a Senior PHP Engineer to join our team.');

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a Laravel posting passes', function () {
    $verdict = $this->filter->check('5+ years of experience with Laravel and Symfony required.');

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a hard requirement for a competing stack with no PHP mention fails', function () {
    $verdict = $this->filter->check('You must have 5+ years of Java/Spring experience.');

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('a hard requirement for Python with no PHP mention fails', function () {
    $verdict = $this->filter->check('This role requires Python and Django experience.');

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('soft language ("Java or similar") passes through to scoring, not a failure', function () {
    $verdict = $this->filter->check('Experience with Java or similar object-oriented languages required.');

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});

test('a hard non-PHP requirement is not a failure if PHP is mentioned elsewhere (polyglot shop)', function () {
    $verdict = $this->filter->check(
        'Our platform is PHP/Laravel, but this team must have 5+ years of Java experience for the legacy service.'
    );

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a posting with no stack information at all is unknown', function () {
    $verdict = $this->filter->check('We are hiring a backend engineer to help scale our platform.');

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});
