<?php

use App\Models\Preference;
use App\Models\User;

test('preference belongs to a user and carries the spec default thresholds', function () {
    $preference = Preference::factory()->create();

    expect($preference->user_id)->not->toBeNull();
    expect($preference->user)->toBeInstanceOf(User::class);
    expect($preference->remote_floor)->toBe(150000);
    expect($preference->onsite_floor)->toBe(180000);
    expect($preference->hard_min)->toBe(140000);
    expect($preference->onsite_states)->toBe(['IL']);
});

test('resume_hash is computed automatically from resume_text on save', function () {
    $preference = Preference::factory()->create(['resume_text' => 'PHP, Laravel, 10 years']);

    expect($preference->resume_hash)->toBe(hash('sha256', 'PHP, Laravel, 10 years'));

    $preference->update(['resume_text' => 'Updated resume']);

    expect($preference->resume_hash)->toBe(hash('sha256', 'Updated resume'));
});

test('resume_hash is null when there is no resume text', function () {
    $preference = Preference::factory()->create(['resume_text' => null]);

    expect($preference->resume_hash)->toBeNull();
});
