<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\ListingStatusHistory;
use App\Models\User;

function makeV1Fixture(array $postings): string
{
    $path = tempnam(sys_get_temp_dir(), 'v1-magpie-').'.sqlite';
    $pdo = new PDO('sqlite:'.$path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec(<<<'SQL'
        CREATE TABLE postings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            company TEXT NOT NULL,
            url TEXT NOT NULL UNIQUE,
            location TEXT,
            remote_type TEXT,
            raw_description TEXT NOT NULL,
            fit_rating TEXT NOT NULL,
            fit_reasoning TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'new',
            notes TEXT,
            source_email_id TEXT NOT NULL,
            date_received TEXT NOT NULL,
            first_seen TEXT NOT NULL,
            fit_commentary TEXT,
            content_source TEXT,
            content_source_url TEXT
        )
        SQL);

    $stmt = $pdo->prepare(
        'INSERT INTO postings (title, company, url, location, remote_type, raw_description, fit_rating, fit_reasoning, status, notes, source_email_id, date_received, first_seen, fit_commentary)
         VALUES (:title, :company, :url, :location, :remote_type, :raw_description, :fit_rating, :fit_reasoning, :status, :notes, :source_email_id, :date_received, :first_seen, :fit_commentary)',
    );

    foreach ($postings as $posting) {
        $stmt->execute([
            'title' => $posting['title'],
            'company' => $posting['company'],
            'url' => $posting['url'],
            'location' => $posting['location'] ?? null,
            'remote_type' => $posting['remote_type'] ?? null,
            'raw_description' => $posting['raw_description'] ?? 'Some description',
            'fit_rating' => $posting['fit_rating'] ?? 'Strong',
            'fit_reasoning' => $posting['fit_reasoning'] ?? 'Good fit',
            'status' => $posting['status'],
            'notes' => $posting['notes'] ?? null,
            'source_email_id' => 'abc123',
            'date_received' => '2026-09-03T21:03:04Z',
            'first_seen' => $posting['first_seen'] ?? '2026-09-04T00:11:27Z',
            'fit_commentary' => $posting['fit_commentary'] ?? null,
        ]);
    }

    return $path;
}

test('only applied and not_interested postings are imported, never new', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);

    $path = makeV1Fixture([
        ['title' => 'Senior PHP Engineer', 'company' => 'Acme', 'url' => 'https://example.com/jobs/1', 'status' => 'applied', 'remote_type' => 'Remote', 'notes' => 'Applied via referral'],
        ['title' => 'Backend Dev', 'company' => 'Widgets Co', 'url' => 'https://example.com/jobs/2', 'status' => 'not_interested', 'remote_type' => 'Hybrid'],
        ['title' => 'Should Not Import', 'company' => 'Other Co', 'url' => 'https://example.com/jobs/3', 'status' => 'new'],
    ]);

    $this->artisan('magpie:import-v1', ['path' => $path])->assertSuccessful();

    expect(Listing::count())->toBe(2);

    $applied = Listing::where('title', 'Senior PHP Engineer')->first();
    expect($applied->state)->toBe('scored');
    expect($applied->status)->toBe('applied');
    expect($applied->status_reason)->toBe('Applied via referral');
    expect($applied->remote_type)->toBe('remote');
    expect($applied->user_id)->toBe($user->id);

    $notInterested = Listing::where('title', 'Backend Dev')->first();
    expect($notInterested->status)->toBe('not_interested');
    expect($notInterested->remote_type)->toBe('hybrid');

    expect(ListingScore::where('listing_id', $applied->id)->first()->rating)->toBe('Strong');
    expect(ListingStatusHistory::where('listing_id', $applied->id)->where('source', 'import')->exists())->toBeTrue();

    expect(Listing::where('title', 'Should Not Import')->exists())->toBeFalse();
});

test('running the import twice does not duplicate listings', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $path = makeV1Fixture([
        ['title' => 'Senior PHP Engineer', 'company' => 'Acme', 'url' => 'https://example.com/jobs/1', 'status' => 'applied'],
    ]);

    $this->artisan('magpie:import-v1', ['path' => $path])->assertSuccessful();
    $this->artisan('magpie:import-v1', ['path' => $path])->assertSuccessful();

    expect(Listing::count())->toBe(1);
});

test('a still-untriaged duplicate (2.0 already re-discovered it) is updated with v1\'s historical verdict, not left as new', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);
    $existing = Listing::factory()->for($user)->create(['canonical_url' => 'https://example.com/jobs/1', 'status' => 'new', 'state' => 'new']);

    $path = makeV1Fixture([
        ['title' => 'Senior PHP Engineer', 'company' => 'Acme', 'url' => 'https://example.com/jobs/1', 'status' => 'applied', 'fit_rating' => 'Strong'],
    ]);

    $this->artisan('magpie:import-v1', ['path' => $path])->assertSuccessful();

    expect(Listing::count())->toBe(1);

    $existing->refresh();
    expect($existing->status)->toBe('applied');
    expect($existing->state)->toBe('scored');
    expect(ListingScore::where('listing_id', $existing->id)->first()->rating)->toBe('Strong');
});

test('a duplicate 2.0 has already triaged itself is left alone, not overwritten by older v1 history', function () {
    $user = User::factory()->create(['email' => config('magpie.admin_email')]);
    $existing = Listing::factory()->for($user)->create([
        'canonical_url' => 'https://example.com/jobs/1',
        'status' => 'not_interested',
        'status_reason' => "2.0's own reason",
        'state' => 'scored',
    ]);

    $path = makeV1Fixture([
        ['title' => 'Senior PHP Engineer', 'company' => 'Acme', 'url' => 'https://example.com/jobs/1', 'status' => 'applied'],
    ]);

    $this->artisan('magpie:import-v1', ['path' => $path])->assertSuccessful();

    expect(Listing::count())->toBe(1);
    expect(ListingScore::count())->toBe(0);

    $existing->refresh();
    expect($existing->status)->toBe('not_interested');
    expect($existing->status_reason)->toBe("2.0's own reason");
});

test('a missing file fails cleanly', function () {
    User::factory()->create(['email' => config('magpie.admin_email')]);

    $this->artisan('magpie:import-v1', ['path' => '/tmp/does-not-exist.sqlite'])->assertFailed();
});
