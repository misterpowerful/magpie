<?php

declare(strict_types=1);

namespace App\Intake;

use App\Models\Site;
use App\Support\RemoteTypeGuesser;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * LaraJobs RSS (larajobs.com/feed). Well-structured custom job:* fields,
 * but <description>/<content:encoded> are typically empty — this
 * source needs the generic enrichment stage (ListingEnrichmentService)
 * to get the full body before hard filters or scoring can run on it.
 */
final class LaraJobsDriver implements IntakeDriver
{
    private const JOB_NS = 'https://larajobs.com';

    public function fetch(Site $site): array
    {
        return $this->parse($site, Http::get($site->url)->throw());
    }

    public function headers(): array
    {
        return [];
    }

    public function parse(Site $site, Response $response): array
    {
        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            return [];
        }

        $stubs = [];

        foreach ($xml->channel->item as $item) {
            $job = $item->children(self::JOB_NS);
            $location = (string) $job->location;

            $stubs[] = [
                'url' => (string) $item->link,
                'company' => (string) $job->company ?: null,
                'title' => (string) $item->title,
                'location_text' => $location !== '' ? $location : null,
                'remote_type' => RemoteTypeGuesser::guess($location),
                'raw_text' => trim(strip_tags(html_entity_decode((string) $item->description))) ?: null,
            ];
        }

        return $stubs;
    }
}
