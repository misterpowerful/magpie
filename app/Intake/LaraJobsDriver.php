<?php

namespace App\Intake;

use App\Models\Site;
use App\Support\RemoteTypeGuesser;
use Illuminate\Support\Facades\Http;

/**
 * LaraJobs RSS (larajobs.com/feed). Well-structured custom job:* fields,
 * but <description>/<content:encoded> are typically empty — this
 * source needs the HTML/enrichment driver (a later ticket) to get the
 * full body before hard filters or scoring can run on it.
 */
class LaraJobsDriver implements IntakeDriver
{
    private const JOB_NS = 'https://larajobs.com';

    public function fetch(Site $site): array
    {
        $response = Http::get($site->url)->throw();

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
