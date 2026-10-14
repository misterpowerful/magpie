<?php

declare(strict_types=1);

use App\Filters\FilterResult;
use App\Filters\SalaryFilter;
use App\Models\Preference;
use App\Salary\SalaryParser;

beforeEach(function () {
    $this->filter = new SalaryFilter;
    $this->parser = new SalaryParser;
    // hard_min 140000, onsite_floor 180000 (spec defaults), unsaved model so no DB needed.
    $this->preferences = new Preference;
});

test('a remote listing above hard_min passes', function () {
    $result = $this->parser->parse('Base salary $150,000 - $170,000 per year, fully remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a remote listing below hard_min fails', function () {
    $result = $this->parser->parse('Base salary $100,000 - $120,000 per year, fully remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('an onsite listing must clear the higher onsite floor', function () {
    $result = $this->parser->parse('Base salary $150,000 - $170,000 per year, on-site in Chicago.');

    $verdict = $this->filter->check($result, 'onsite', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('an onsite listing above the onsite floor passes', function () {
    $result = $this->parser->parse('Base salary $185,000 - $200,000 per year, on-site in Chicago.');

    $verdict = $this->filter->check($result, 'onsite', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a hybrid listing uses the onsite floor, not the remote hard_min', function () {
    $result = $this->parser->parse('Base salary $150,000 per year, hybrid.');

    $verdict = $this->filter->check($result, 'hybrid', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('"up to X" below the floor never fails, only unknown', function () {
    $result = $this->parser->parse('Salary up to $130,000 depending on experience, remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
    expect($verdict->flags['open_ended'])->toBeTrue();
});

test('"up to X" above the floor passes outright', function () {
    $result = $this->parser->parse('Salary up to $200,000 depending on experience, remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
});

test('a tiered range passes if any tier reaches the floor', function () {
    $text = "Illinois: \$145,000 - \$165,000 base salary.\nRest of US: \$120,000 - \$135,000 base salary.";
    $result = $this->parser->parse($text);

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::PASS);
    expect($verdict->flags['tiered'])->toBeTrue();
});

test('a tiered range fails only when every tier is below the floor', function () {
    $text = "Illinois: \$100,000 - \$110,000 base salary.\nRest of US: \$90,000 - \$100,000 base salary.";
    $result = $this->parser->parse($text);

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::FAIL);
});

test('no parseable salary is unknown, not a failure, and is flagged', function () {
    $result = $this->parser->parse('Compensation is competitive and commensurate with experience.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
    expect($verdict->flags['salary_unknown'])->toBeTrue();
});

test('an OTE-only figure with no base is unknown, not a failure', function () {
    $result = $this->parser->parse('OTE of $250,000 with uncapped commission, remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->verdict)->toBe(FilterResult::UNKNOWN);
});

test('a base figure with no OTE mentioned is flagged bonus_unknown', function () {
    $result = $this->parser->parse('Base salary $150,000 per year, remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->flags['bonus_unknown'] ?? false)->toBeTrue();
});

test('a base figure with OTE also mentioned is not flagged bonus_unknown', function () {
    $result = $this->parser->parse('Base salary $150,000 plus OTE of $200,000, remote.');

    $verdict = $this->filter->check($result, 'remote', $this->preferences);

    expect($verdict->flags['bonus_unknown'] ?? false)->toBeFalse();
});
