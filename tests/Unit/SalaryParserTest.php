<?php

use App\Salary\SalaryParser;

beforeEach(function () {
    $this->parser = new SalaryParser;
});

test('parses a plain annual range near a salary anchor', function () {
    $result = $this->parser->parse('We offer a base salary of $140,000 - $160,000 depending on experience.');

    expect($result->confidence)->toBe('high');
    expect($result->baseTiers())->toHaveCount(1);
    expect($result->baseTiers()[0]['low'])->toBe(140000.0);
    expect($result->baseTiers()[0]['high'])->toBe(160000.0);
    expect($result->baseTiers()[0]['period'])->toBe('year');
});

test('parses k-shorthand ranges', function () {
    $result = $this->parser->parse('Compensation: $140k-$160k per year.');

    expect($result->baseTiers()[0]['low'])->toBe(140000.0);
    expect($result->baseTiers()[0]['high'])->toBe(160000.0);
});

test('"up to $X" is a ceiling only and never fails on its own', function () {
    $result = $this->parser->parse('Salary up to $180,000 based on experience.');

    expect($result->baseTiers()[0]['low'])->toBeNull();
    expect($result->baseTiers()[0]['high'])->toBe(180000.0);
    expect($result->flags['open_ended'])->toBeTrue();
});

test('"$X+" is a floor only and never fails on its own', function () {
    $result = $this->parser->parse('Base salary $150,000+ depending on seniority.');

    expect($result->baseTiers()[0]['low'])->toBe(150000.0);
    expect($result->baseTiers()[0]['high'])->toBeNull();
    expect($result->flags['open_ended'])->toBeTrue();
});

test('OTE figures are flagged and not read as base salary', function () {
    $result = $this->parser->parse('Base salary $120,000 plus OTE of $200,000 with uncapped commission.');

    expect($result->flags['ote'])->toBeTrue();
    expect($result->flags['commission'])->toBeTrue();
    expect($result->baseTiers())->toHaveCount(1);
    expect($result->baseTiers()[0]['low'])->toBe(120000.0);
});

test('equity mentions are flagged but do not produce a salary tier', function () {
    $result = $this->parser->parse('Base salary $130,000 plus significant equity in the company.');

    expect($result->flags['equity'])->toBeTrue();
    expect($result->baseTiers())->toHaveCount(1);
});

test('hourly rates are normalized to an annual equivalent (x2080)', function () {
    $result = $this->parser->parse('This role pays $75/hr.');

    expect($result->flags['hourly'])->toBeTrue();
    expect($result->baseTiers()[0]['low'])->toBe(75 * 2080.0);
    expect($result->baseTiers()[0]['period'])->toBe('hour');
});

test('hourly ranges are normalized on both ends', function () {
    $result = $this->parser->parse('Pay range: $60 - $80 per hour.');

    expect($result->baseTiers()[0]['low'])->toBe(60 * 2080.0);
    expect($result->baseTiers()[0]['high'])->toBe(80 * 2080.0);
});

test('a 401k match is ignored as a non-salary dollar amount', function () {
    $result = $this->parser->parse('We offer a 401k match up to $3,000 annually and great benefits.');

    expect($result->confidence)->toBe('none');
    expect($result->baseTiers())->toHaveCount(0);
});

test('a signing bonus is ignored as a non-salary dollar amount', function () {
    $result = $this->parser->parse('This role includes a $10,000 signing bonus.');

    expect($result->baseTiers())->toHaveCount(0);
});

test('startup funding figures are ignored as non-salary dollar amounts', function () {
    $result = $this->parser->parse('We recently raised $15,000,000 in Series B funding.');

    expect($result->baseTiers())->toHaveCount(0);
});

test('a disqualifying term near an explicit salary anchor still parses the salary', function () {
    $result = $this->parser->parse('Base salary $140,000 plus a $5,000 signing bonus and 401k match.');

    expect($result->baseTiers())->toHaveCount(1);
    expect($result->baseTiers()[0]['low'])->toBe(140000.0);
});

test('tiered location-based ranges are all captured with labels', function () {
    $text = "Illinois: \$150,000 - \$170,000 base salary.\nRest of US: \$140,000 - \$160,000 base salary.";

    $result = $this->parser->parse($text);

    expect($result->flags['tiered'])->toBeTrue();
    expect($result->baseTiers())->toHaveCount(2);

    $labels = array_column($result->baseTiers(), 'label');
    expect($labels)->toContain('Illinois');
    expect($labels)->toContain('Rest of US');
});

test('non-USD amounts are flagged and excluded from base tiers', function () {
    $result = $this->parser->parse('Salary: £90,000 per year.');

    expect($result->flags['non_usd'])->toBeTrue();
    expect($result->baseTiers())->toHaveCount(0);
});

test('a bare dollar amount with no anchor and no period is not treated as salary', function () {
    $result = $this->parser->parse('The company raised $500,000 from investors last year but salary is negotiable.');

    expect($result->baseTiers())->toHaveCount(0);
});

test('text with no dollar amounts at all has no parseable salary', function () {
    $result = $this->parser->parse('Compensation is competitive and commensurate with experience.');

    expect($result->confidence)->toBe('none');
    expect($result->hasParseableSalary())->toBeFalse();
});

test('highestTop reflects the top of the highest stated range', function () {
    $text = "Illinois: \$150,000 - \$170,000 base salary.\nRest of US: \$140,000 - \$160,000 base salary.";

    $result = $this->parser->parse($text);

    expect($result->highestTop())->toBe(170000.0);
});

test('lowestTierMidpoint uses the lowest applicable tier', function () {
    $text = "Illinois: \$150,000 - \$170,000 base salary.\nRest of US: \$140,000 - \$160,000 base salary.";

    $result = $this->parser->parse($text);

    expect($result->lowestTierMidpoint())->toBe(150000.0);
});
