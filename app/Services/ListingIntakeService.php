<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use App\Support\UrlCanonicalizer;
use Illuminate\Support\Carbon;

final class ListingIntakeService
{
    /**
     * Find an existing listing for this user (by canonical URL, falling
     * back to a company+title hash — the same job posted under a
     * different URL), or create a new stub.
     *
     * @param  array{url: string, company?: ?string, title: string, location_text?: ?string, remote_type?: ?string, raw_text?: ?string}  $stub
     */
    public function firstOrCreateFromStub(int $userId, array $stub): Listing
    {
        $canonicalUrl = UrlCanonicalizer::canonicalize($stub['url']);
        $contentHash = self::contentHash($stub['company'] ?? null, $stub['title']);

        $existing = Listing::where('user_id', $userId)
            ->where(function ($query) use ($canonicalUrl, $contentHash) {
                $query->where('canonical_url', $canonicalUrl)
                    ->orWhere('content_hash', $contentHash);
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        return Listing::create([
            'user_id' => $userId,
            'canonical_url' => $canonicalUrl,
            'company' => $stub['company'] ?? null,
            'title' => $stub['title'],
            'location_text' => $stub['location_text'] ?? null,
            'remote_type' => $stub['remote_type'] ?? null,
            'raw_text' => $stub['raw_text'] ?? null,
            'content_hash' => $contentHash,
            'state' => 'new',
            'status' => 'new',
            'first_seen_at' => Carbon::now(),
        ]);
    }

    /**
     * Public so the v1 data importer (one-time migration, spec section
     * 12 "Deployment and cutover") can dedupe imported rows the same
     * way live intake would — if a site later surfaces the "same" job
     * again, it should match this imported row, not create a duplicate.
     */
    public static function contentHash(?string $company, string $title): string
    {
        $normalize = fn (?string $value) => preg_replace('/\s+/', ' ', trim(strtolower((string) $value)));

        return hash('sha256', $normalize($company).'|'.$normalize($title));
    }
}
