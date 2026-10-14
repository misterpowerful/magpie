<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;
use App\Support\RemoteTypeGuesser;
use Illuminate\Support\Facades\Http;

/**
 * Remote OK JSON API. The first array element is a metadata/legal
 * banner, not a job — skip it.
 */
final class RemoteOkDriver implements IntakeDriver
{
    public function fetch(Site $site): array
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($site->url)->throw();
        $jobs = $response->json() ?? [];

        $stubs = [];

        foreach ($jobs as $job) {
            if (! isset($job['position'])) {
                continue; // the legal/metadata banner entry
            }

            $location = (string) ($job['location'] ?? '');

            $stubs[] = [
                'url' => $job['url'] ?? $job['apply_url'],
                'company' => trim($job['company'] ?? '') ?: null,
                'title' => $job['position'],
                'location_text' => $location !== '' ? $location : null,
                'remote_type' => RemoteTypeGuesser::guess($location) ?? 'remote',
                'raw_text' => trim(strip_tags(html_entity_decode((string) ($job['description'] ?? '')))) ?: null,
            ];
        }

        return $stubs;
    }
}
