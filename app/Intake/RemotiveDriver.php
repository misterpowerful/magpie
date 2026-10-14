<?php

namespace App\Intake;

use App\Models\Site;
use App\Support\RemoteTypeGuesser;
use Illuminate\Support\Facades\Http;

/**
 * Remotive JSON API. Its ToS asks for at most ~4 requests/day and no
 * public redistribution of listings; our schedule (3x/week plus a
 * catch-up) is well under that, and this is a private single-user
 * dashboard, not a public job board republishing their listings.
 */
class RemotiveDriver implements IntakeDriver
{
    public function fetch(Site $site): array
    {
        $response = Http::get($site->url)->throw();
        $jobs = $response->json('jobs') ?? [];

        $stubs = [];

        foreach ($jobs as $job) {
            $location = (string) ($job['candidate_required_location'] ?? '');

            $stubs[] = [
                'url' => $job['url'],
                'company' => trim($job['company_name'] ?? '') ?: null,
                'title' => $job['title'],
                'location_text' => $location !== '' ? $location : null,
                'remote_type' => RemoteTypeGuesser::guess($location) ?? 'remote',
                'raw_text' => trim(strip_tags(html_entity_decode((string) ($job['description'] ?? '')))) ?: null,
            ];
        }

        return $stubs;
    }
}
