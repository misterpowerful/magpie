<?php

namespace App\Inbox;

use App\Contracts\LlmDriver;
use Illuminate\Support\Facades\Log;

/**
 * Spec section 5 "email": "LLM (Haiku) only as a fallback for unknown
 * formats." Always matches (it's the catch-all), so it must come last
 * in the registry. Spec assumes alert bodies don't carry stack or
 * salary, so these stubs get raw_text = null — the html/enrichment
 * driver (a later ticket) fetches the real posting from `url`.
 */
class HaikuFallbackParser implements EmailParser
{
    public function __construct(
        private readonly LlmDriver $driver,
    ) {}

    public function canParse(IncomingEmail $email): bool
    {
        return true;
    }

    public function parse(IncomingEmail $email): array
    {
        $body = $email->bodyText ?: trim(strip_tags(html_entity_decode($email->bodyHtml ?? '')));

        if (trim($body) === '') {
            return [];
        }

        $prompt = $this->buildPrompt($email, $body);
        $response = $this->driver->complete($prompt, config('magpie.llm.triage_model'));

        $jobs = json_decode(trim($response), true);

        if (! is_array($jobs)) {
            Log::warning('Haiku fallback parser got non-JSON response', ['subject' => $email->subject]);

            return [];
        }

        return array_values(array_filter(array_map(fn ($job) => $this->toStub($job), $jobs)));
    }

    private function buildPrompt(IncomingEmail $email, string $body): string
    {
        return <<<TEXT
            This is a job-alert email from an unrecognized sender. Extract
            every distinct job listed in it. Respond with ONLY a JSON array,
            no prose outside it, where each element is exactly:

            {"url": "the job's direct URL", "company": "company name or null", "title": "job title", "location_text": "location text as written, or null", "remote_type": "remote"|"onsite"|"hybrid"|null}

            If there are no jobs in this email, respond with an empty array: [].

            Subject: {$email->subject}

            Body:
            {$body}
            TEXT;
    }

    /**
     * @return array{url: string, company: ?string, title: string, location_text: ?string, remote_type: ?string, raw_text: ?string}|null
     */
    private function toStub(mixed $job): ?array
    {
        if (! is_array($job) || empty($job['url']) || empty($job['title'])) {
            return null;
        }

        return [
            'url' => $job['url'],
            'company' => $job['company'] ?? null,
            'title' => $job['title'],
            'location_text' => $job['location_text'] ?? null,
            'remote_type' => $job['remote_type'] ?? null,
            'raw_text' => null,
        ];
    }
}
