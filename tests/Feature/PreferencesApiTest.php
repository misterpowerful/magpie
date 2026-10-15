<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('preference endpoints require authentication', function () {
    $this->getJson('/api/preferences')->assertUnauthorized();
    $this->putJson('/api/preferences', [])->assertUnauthorized();
    $this->postJson('/api/preferences/resume', [])->assertUnauthorized();
    $this->postJson('/api/preferences/rescore')->assertUnauthorized();
});

test('showing preferences creates them with defaults, null criteria, and the default criteria text', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/preferences')->assertOk()
        ->assertJsonPath('data.remote_floor', 150000)
        ->assertJsonPath('data.onsite_states', ['IL'])
        ->assertJsonPath('data.scoring_criteria', null)
        ->assertJsonPath('data.default_scoring_criteria', Preference::DEFAULT_SCORING_CRITERIA)
        ->assertJsonPath('data.has_resume_file', false);

    expect(Preference::where('user_id', $user->id)->count())->toBe(1);
});

test('preferences and criteria can be updated; states are upper-cased and blank criteria resets to default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/preferences', [
        'remote_floor' => 160000,
        'onsite_states' => ['il', ' wi '],
        'include_stack' => ['PHP', 'Laravel'],
        'scoring_criteria' => 'Prefer small product teams.',
    ])->assertOk()
        ->assertJsonPath('data.remote_floor', 160000)
        ->assertJsonPath('data.onsite_states', ['IL', 'WI'])
        ->assertJsonPath('data.scoring_criteria', 'Prefer small product teams.');

    $this->actingAs($user)->putJson('/api/preferences', ['scoring_criteria' => '   '])
        ->assertJsonPath('data.scoring_criteria', null);

    expect(Preference::where('user_id', $user->id)->first()->effectiveScoringCriteria())->toBe(Preference::DEFAULT_SCORING_CRITERIA);
});

test('invalid preference values are rejected', function () {
    $this->actingAs(User::factory()->create())->putJson('/api/preferences', [
        'remote_floor' => -5,
        'onsite_states' => ['Illinois'],
    ])->assertUnprocessable()->assertJsonValidationErrors(['remote_floor', 'onsite_states.0']);
});

test('uploading a text resume stores the file and extracts its text', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->createWithContent('resume.txt', "Jane Doe\r\nSenior PHP Engineer   \n\n\n\nLaravel, Vue");

    $response = $this->actingAs($user)->post('/api/preferences/resume', ['resume' => $file], ['Accept' => 'application/json'])->assertOk();

    expect($response->json('data.resume_text'))->toBe("Jane Doe\nSenior PHP Engineer\n\nLaravel, Vue");
    expect($response->json('data.resume_filename'))->toBe('resume.txt');
    expect($response->json('data.has_resume_file'))->toBeTrue();

    $preferences = Preference::where('user_id', $user->id)->first();
    Storage::disk('local')->assertExists($preferences->resume_path);
    expect($preferences->resume_hash)->toBe(hash('sha256', $preferences->resume_text));
});

test('uploading a .docx extracts its paragraphs', function () {
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        .'<w:p><w:r><w:t>Jane Doe</w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">Senior </w:t></w:r><w:r><w:t>PHP Engineer</w:t></w:r></w:p>'
        .'</w:body></w:document>');
    $zip->close();

    $file = new UploadedFile($path, 'resume.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $this->actingAs(User::factory()->create())->post('/api/preferences/resume', ['resume' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.resume_text', "Jane Doe\nSenior PHP Engineer");
});

test('uploading a PDF extracts its text', function () {
    $content = "BT /F1 12 Tf 72 712 Td (Senior PHP Engineer) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    $file = UploadedFile::fake()->createWithContent('resume.pdf', $pdf);

    $response = $this->actingAs(User::factory()->create())->post('/api/preferences/resume', ['resume' => $file], ['Accept' => 'application/json'])->assertOk();

    expect($response->json('data.resume_text'))->toContain('Senior PHP Engineer');
});

test('an unsupported or empty resume file is rejected with a readable message', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/api/preferences/resume', ['resume' => UploadedFile::fake()->create('resume.exe', 10)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('resume');

    $this->actingAs($user)->post('/api/preferences/resume', ['resume' => UploadedFile::fake()->createWithContent('resume.txt', "  \n ")], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('resume');
});

test('a new upload replaces the old file and the original can be downloaded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/api/preferences/resume', ['resume' => UploadedFile::fake()->createWithContent('old.txt', 'Old resume')], ['Accept' => 'application/json']);
    $oldPath = Preference::where('user_id', $user->id)->first()->resume_path;

    $this->travel(2)->seconds();
    $this->actingAs($user)->post('/api/preferences/resume', ['resume' => UploadedFile::fake()->createWithContent('new.txt', 'New resume')], ['Accept' => 'application/json']);
    $preferences = Preference::where('user_id', $user->id)->first();

    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($preferences->resume_path);
    expect($preferences->resume_text)->toBe('New resume');

    $this->actingAs($user)->get('/api/preferences/resume')->assertOk()->assertDownload('new.txt');
});

test('downloading with no resume on file is a 404', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/preferences/resume')->assertNotFound();
});

test('rescore queues only pending listings scored against old criteria or an old resume', function () {
    $user = User::factory()->create();
    $preferences = Preference::factory()->create(['user_id' => $user->id, 'resume_text' => 'Current resume']);

    $score = function (Listing $listing, string $prefsHash, ?string $resumeHash) {
        ListingScore::create([
            'listing_id' => $listing->id, 'rating' => 'Moderate', 'reasons' => [], 'flags' => [], 'commentary' => null,
            'prefs_hash' => $prefsHash, 'resume_hash' => $resumeHash, 'model' => 'claude-sonnet-5-5', 'scored_at' => now(),
        ]);
    };

    $current = Listing::factory()->for($user)->create(['state' => 'scored', 'status' => 'new']);
    $score($current, $preferences->scoringHash(), $preferences->resume_hash);

    $oldResume = Listing::factory()->for($user)->create(['state' => 'scored', 'status' => 'new']);
    $score($oldResume, $preferences->scoringHash(), hash('sha256', 'Old resume'));

    $noResume = Listing::factory()->for($user)->create(['state' => 'scored', 'status' => 'new']);
    $score($noResume, $preferences->scoringHash(), null);

    $oldCriteria = Listing::factory()->for($user)->create(['state' => 'scored', 'status' => 'new']);
    $score($oldCriteria, 'old-prefs-hash', $preferences->resume_hash);

    $applied = Listing::factory()->for($user)->create(['state' => 'scored', 'status' => 'applied']);
    $score($applied, 'old-prefs-hash', null);

    $this->actingAs($user)->getJson('/api/preferences')->assertJsonPath('data.stale_scored_count', 3);

    $this->actingAs($user)->postJson('/api/preferences/rescore')->assertOk()->assertJsonPath('data.queued', 3);

    expect($current->fresh()->state)->toBe('scored');
    expect($oldResume->fresh()->state)->toBe('filtered_in');
    expect($noResume->fresh()->state)->toBe('filtered_in');
    expect($oldCriteria->fresh()->state)->toBe('filtered_in');
    expect($applied->fresh()->state)->toBe('scored');
    expect($oldResume->fresh()->score)->not->toBeNull();
});
