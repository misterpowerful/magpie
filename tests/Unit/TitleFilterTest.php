<?php

declare(strict_types=1);

use App\Filters\FilterResult;
use App\Filters\TitleFilter;
use App\Models\Preference;

beforeEach(function () {
    $this->filter = new TitleFilter;
});

test('a title matching an exclude pattern fails', function () {
    $preferences = new Preference(['exclude_titles' => ['Intern', 'Junior']]);

    $verdict = $this->filter->check('Junior PHP Developer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('a title matching an include pattern passes', function () {
    $preferences = new Preference(['include_titles' => ['Senior', 'Staff', 'Lead']]);

    $verdict = $this->filter->check('Senior Backend Engineer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('an include list with no match is unknown, not a failure', function () {
    $preferences = new Preference(['include_titles' => ['Senior', 'Staff', 'Lead']]);

    $verdict = $this->filter->check('Backend Engineer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});

test('no include or exclude preferences set is unknown', function () {
    $preferences = new Preference;

    $verdict = $this->filter->check('Backend Engineer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});

test('exclude takes priority over include when both would otherwise match', function () {
    $preferences = new Preference([
        'include_titles' => ['Engineer'],
        'exclude_titles' => ['Junior'],
    ]);

    $verdict = $this->filter->check('Junior Engineer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('an invalid regex pattern is skipped rather than crashing', function () {
    $preferences = new Preference(['exclude_titles' => ['[invalid(regex']]);

    $verdict = $this->filter->check('Senior Engineer', $preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});
