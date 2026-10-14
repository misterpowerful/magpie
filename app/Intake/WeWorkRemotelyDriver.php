<?php

namespace App\Intake;

use App\Models\Site;
use App\Support\RemoteTypeGuesser;
use Illuminate\Support\Facades\Http;

/**
 * We Work Remotely RSS. Titles are "Company: Job Title"; the feed's
 * <description> carries the full HTML job body, so this source needs
 * no separate enrichment fetch.
 */
class WeWorkRemotelyDriver implements IntakeDriver
{
    public function fetch(Site $site): array
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($site->url)->throw();

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            return [];
        }

        $stubs = [];

        foreach ($xml->channel->item as $item) {
            [$company, $title] = $this->splitTitle((string) $item->title);
            $region = (string) $item->region;

            $stubs[] = [
                'url' => (string) $item->link,
                'company' => $company,
                'title' => $title,
                'location_text' => $region !== '' ? $region : null,
                'remote_type' => RemoteTypeGuesser::guess($region) ?? 'remote',
                'raw_text' => trim(strip_tags(html_entity_decode((string) $item->description))),
            ];
        }

        return $stubs;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function splitTitle(string $title): array
    {
        if (str_contains($title, ':')) {
            [$company, $rest] = explode(':', $title, 2);

            return [trim($company), trim($rest)];
        }

        return [null, trim($title)];
    }
}
