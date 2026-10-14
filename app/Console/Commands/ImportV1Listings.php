<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\ListingScore;
use App\Models\ListingStatusHistory;
use App\Models\User;
use App\Services\ListingIntakeService;
use App\Support\UrlCanonicalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PDO;

/**
 * One-time v1 -> v2 data import (spec section 12 "Deployment and
 * cutover" step 3): "at minimum every applied and not-interested
 * listing with its status, notes and dates, so old listings do not
 * resurface as new. Imported rows are never rescored." state is set
 * to 'scored' directly — the run orchestrator's scoring stage only
 * ever queries state='filtered_in', so an imported row is permanently
 * out of its way regardless of status.
 *
 * Takes an explicit path (never touches v1's live database directly)
 * and dedupes the same way live intake does, so re-running it after
 * some listings already came in through 2.0's own intake is safe.
 */
#[Signature('magpie:import-v1 {path : Path to a v1 magpie.db SQLite file}')]
#[Description("Import v1's applied/not_interested postings so they don't resurface as new")]
class ImportV1Listings extends Command
{
    /** @var array<string, string> */
    private const REMOTE_TYPE_MAP = [
        'Remote' => 'remote',
        'Hybrid' => 'hybrid',
        'On-site' => 'onsite',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        $user = User::where('email', config('magpie.admin_email'))->first();

        if (! $user) {
            $this->error('No admin user found — check ADMIN_EMAIL and run the seeder.');

            return self::FAILURE;
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statement = $pdo->query("SELECT * FROM postings WHERE status IN ('applied', 'not_interested')");
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            match ($this->importRow($user->id, $row)) {
                'imported' => $imported++,
                'updated' => $updated++,
                'skipped' => $skipped++,
            };
        }

        $this->info("Imported: {$imported}, updated (already existed as 'new'): {$updated}, skipped (already triaged): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'imported'|'updated'|'skipped'
     */
    private function importRow(int $userId, array $row): string
    {
        $canonicalUrl = UrlCanonicalizer::canonicalize((string) $row['url']);
        $contentHash = ListingIntakeService::contentHash($row['company'] ?? null, (string) $row['title']);
        $firstSeenAt = Carbon::parse((string) $row['first_seen']);

        $existing = Listing::where('user_id', $userId)
            ->where(function ($query) use ($canonicalUrl, $contentHash) {
                $query->where('canonical_url', $canonicalUrl)->orWhere('content_hash', $contentHash);
            })
            ->first();

        // A listing 2.0's own intake already re-discovered (still
        // untriaged) gets v1's historical verdict applied, so it
        // doesn't sit in the dashboard looking like a fresh "new"
        // match the owner has actually already triaged once before.
        // One 2.0 has already triaged itself (status no longer 'new')
        // is left alone — that's more recent than v1's history.
        if ($existing && $existing->status !== 'new') {
            return 'skipped';
        }

        $listing = $existing
            ? tap($existing)->update([
                'remote_type' => self::REMOTE_TYPE_MAP[$row['remote_type']] ?? $existing->remote_type,
                'raw_text' => $existing->raw_text ?? $row['raw_description'],
                'state' => 'scored',
                'status' => $row['status'],
                'status_reason' => $row['notes'],
            ])
            : Listing::create([
                'user_id' => $userId,
                'canonical_url' => $canonicalUrl,
                'company' => $row['company'],
                'title' => $row['title'],
                'location_text' => $row['location'],
                'remote_type' => self::REMOTE_TYPE_MAP[$row['remote_type']] ?? null,
                'raw_text' => $row['raw_description'],
                'content_hash' => $contentHash,
                'state' => 'scored',
                'status' => $row['status'],
                'status_reason' => $row['notes'],
                'first_seen_at' => $firstSeenAt,
            ]);

        ListingScore::updateOrCreate(
            ['listing_id' => $listing->id],
            [
                'rating' => $row['fit_rating'],
                'reasons' => [$row['fit_reasoning']],
                'flags' => [],
                'commentary' => $row['fit_commentary'],
                'prefs_hash' => 'imported-v1',
                'resume_hash' => 'imported-v1',
                'model' => 'magpie-v1',
                'scored_at' => $firstSeenAt,
            ],
        );

        $history = new ListingStatusHistory([
            'listing_id' => $listing->id,
            'status' => $row['status'],
            'source' => 'import',
            'reason' => $row['notes'],
        ]);
        $history->created_at = $firstSeenAt;
        $history->save();

        return $existing ? 'updated' : 'imported';
    }
}
