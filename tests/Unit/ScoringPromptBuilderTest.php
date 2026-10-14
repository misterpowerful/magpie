<?php

declare(strict_types=1);

use App\Llm\ScoringPromptBuilder;
use App\Models\Listing;
use App\Models\Preference;

beforeEach(function () {
    $this->builder = new ScoringPromptBuilder;
});

test('the static prefix is byte-identical for two different listings under the same preferences', function () {
    $preferences = new Preference(['resume_text' => 'Senior PHP engineer, 10 years.']);

    $a = $this->builder->staticPrefix($preferences);
    $b = $this->builder->staticPrefix($preferences);

    expect($a)->toBe($b);
});

test('the static prefix changes when the resume text changes', function () {
    $prefsA = new Preference(['resume_text' => 'Resume A']);
    $prefsB = new Preference(['resume_text' => 'Resume B']);

    expect($this->builder->staticPrefix($prefsA))->not->toBe($this->builder->staticPrefix($prefsB));
});

test('the posting section comes after the static prefix in the full prompt', function () {
    $preferences = new Preference(['resume_text' => 'Senior PHP engineer.']);
    $listing = new Listing(['company' => 'Acme', 'title' => 'Backend Engineer', 'remote_type' => 'remote', 'raw_text' => 'We need a PHP engineer.']);

    $prompt = $this->builder->build($listing, $preferences);
    $prefix = $this->builder->staticPrefix($preferences);

    expect(str_starts_with($prompt, $prefix))->toBeTrue();
    expect(strpos($prompt, 'Acme'))->toBeGreaterThan(strlen($prefix) - 1);
});

test('the posting section includes company, title, and remote type', function () {
    $preferences = new Preference;
    $listing = new Listing(['company' => 'Acme', 'title' => 'Backend Engineer', 'remote_type' => 'remote', 'raw_text' => 'Body text.']);

    $posting = $this->builder->postingSection($listing);

    expect($posting)->toContain('Acme');
    expect($posting)->toContain('Backend Engineer');
    expect($posting)->toContain('remote');
});

test('the prompt instructs the model to require commentary only for a Strong rating', function () {
    $preferences = new Preference;

    expect($this->builder->staticPrefix($preferences))->toContain('"commentary" is required for a "Strong" rating');
});
