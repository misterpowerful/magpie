<?php

declare(strict_types=1);

use App\Inbox\ApplicationConfirmationMatcher;

beforeEach(function () {
    $this->matcher = new ApplicationConfirmationMatcher;
});

test('recognizes common confirmation phrasings', function (string $subject, string $body) {
    expect($this->matcher->looksLikeConfirmation($subject, $body))->toBeTrue();
})->with([
    'thank you for applying' => ['Thank you for applying to Acme Corp', ''],
    'received your application' => ['Application update', "We've received your application for the role."],
    'has been received' => ['Re: your application', 'Your application has been received by our team.'],
    'application submitted' => ['Confirmation', 'Your application submitted successfully.'],
    'interest phrasing' => ['Thanks', 'Thank you for your interest in joining Acme Corp.'],
]);

test('does not flag an ordinary job-alert email as a confirmation', function () {
    $subject = 'New remote PHP jobs matching your search';
    $body = 'Senior Laravel Engineer at Acme Corp — apply now!';

    expect($this->matcher->looksLikeConfirmation($subject, $body))->toBeFalse();
});

test('does not flag an unrelated email as a confirmation', function () {
    expect($this->matcher->looksLikeConfirmation('Your receipt from Acme Store', 'Thanks for your purchase.'))->toBeFalse();
});

test('extracts a company name after "applying to"', function () {
    $candidates = $this->matcher->extractCompanyCandidates(
        'Thank you for applying to Acme Corp',
        'We have received your application.',
        'Acme Corp Careers <careers@acme.example>',
    );

    expect($candidates)->toContain('Acme Corp');
});

test('extracts a company name after "application to"', function () {
    $candidates = $this->matcher->extractCompanyCandidates(
        'Application received',
        'Your application to Globex Industries has been received.',
        'no-reply@globex.example',
    );

    expect($candidates)->toContain('Globex Industries');
});

test('extracts a company name from "interest in"', function () {
    $candidates = $this->matcher->extractCompanyCandidates(
        'Thanks for your interest',
        'Thank you for your interest in Initech Solutions.',
        'hr@initech.example',
    );

    expect($candidates)->toContain('Initech Solutions');
});

test('falls back to the from-name with recruiting noise words stripped', function () {
    $candidates = $this->matcher->extractCompanyCandidates(
        'We got your application',
        'Thanks, we will be in touch.',
        'Acme Corp Talent Acquisition',
    );

    expect($candidates)->toContain('Acme Corp');
});

test('does not bleed a capitalized word from the next line into the captured company name', function () {
    // Regression guard: an earlier version of this regex let `\s` (which
    // matches newlines) pull a capitalized word starting the *next*
    // sentence into the capture, e.g. "...Acme Corp\nWe have..." -> "Acme
    // Corp\nWe" instead of stopping at "Acme Corp".
    $candidates = $this->matcher->extractCompanyCandidates(
        'Thank you for applying to Acme Corp',
        "We have received your application.\nWe will follow up soon.",
        'noreply@acme.example',
    );

    expect($candidates)->toContain('Acme Corp');
    expect($candidates)->not->toContain("Acme Corp\nWe");
    foreach ($candidates as $candidate) {
        expect($candidate)->not->toContain("\n");
    }
});

test('matches the trigger phrase regardless of case without losing the capitalization-based company boundary', function () {
    // Regression guard: naively adding a blanket /i flag to make "Thank"
    // match "thank" also case-folds the [A-Z] capture boundary, so it no
    // longer stops at the first lowercase word (over-captures).
    $candidates = $this->matcher->extractCompanyCandidates(
        'Application received',
        'Your application to Globex Industries has been received.',
        'noreply@globex.example',
    );

    expect($candidates)->toContain('Globex Industries');
    expect($candidates)->not->toContain('Globex Industries has been received');
});

test('extracts a company after a capitalized "Interest in" at the start of a sentence', function () {
    $candidates = $this->matcher->extractCompanyCandidates(
        'Re: your application',
        'Interest in Acme Robotics is appreciated — we will follow up soon.',
        'noreply@acme.example',
    );

    expect($candidates)->toContain('Acme Robotics');
});

test('normalizes common corporate suffixes so names compare equal', function () {
    $normalize = fn (string $c) => $this->matcher->normalizeCompany($c);

    expect($normalize('Acme Corp'))->toBe($normalize('ACME Corporation'));
    expect($normalize('Globex LLC'))->toBe($normalize('Globex'));
    expect($normalize('Initech, Inc.'))->toBe($normalize('Initech'));
});
