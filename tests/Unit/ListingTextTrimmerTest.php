<?php

declare(strict_types=1);

use App\Support\ListingTextTrimmer;

beforeEach(function () {
    $this->trimmer = new ListingTextTrimmer;
});

test('strips an equal opportunity employer paragraph', function () {
    $text = "We are hiring a backend engineer.\n\n".
        "ACME Corp is an equal opportunity employer and does not discriminate on the basis of race, color, or religion.\n\n".
        'Base salary $150,000 per year.';

    $result = $this->trimmer->trim($text);

    expect($result)->not->toContain('equal opportunity');
    expect($result)->toContain('Base salary $150,000');
});

test('strips a labeled benefits section until the next real heading', function () {
    $text = "We are hiring a backend engineer.\n\n".
        "Benefits\n\n".
        "Health insurance, dental, and a 401k match.\n\n".
        "Unlimited PTO and a home office stipend.\n\n".
        "Requirements\n\n".
        'PHP and Laravel experience required.';

    $result = $this->trimmer->trim($text);

    expect($result)->not->toContain('Health insurance');
    expect($result)->not->toContain('home office stipend');
    expect($result)->toContain('PHP and Laravel experience');
});

test('keeps the company/mission description since it matters for scoring', function () {
    $text = "We are hiring a backend engineer.\n\n".
        "About Acme: our mission is to make small business accounting effortless for everyone.\n\n".
        'Base salary $150,000 per year.';

    $result = $this->trimmer->trim($text);

    expect($result)->toContain('mission is to make small business accounting effortless');
});

test('strips a visa sponsorship disclaimer', function () {
    $text = "We are hiring a backend engineer.\n\n".
        "We are unable to provide visa sponsorship for this role.\n\n".
        'Base salary $150,000 per year.';

    $result = $this->trimmer->trim($text);

    expect($result)->not->toContain('visa sponsorship');
});

test('leaves ordinary role/requirements text completely untouched', function () {
    $text = "We need a senior PHP engineer with Laravel experience to help scale our platform.\n\n".
        'Requirements: 5+ years PHP, strong SQL skills, experience with REST APIs.';

    $result = $this->trimmer->trim($text);

    expect($result)->toBe($text);
});
