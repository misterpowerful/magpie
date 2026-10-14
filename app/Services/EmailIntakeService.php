<?php

declare(strict_types=1);

namespace App\Services;

use App\Inbox\EmailParserRegistry;
use App\Inbox\MailSource;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Spec section 5 "email": poll the mailbox, parse each message with
 * whichever parser claims it (falling back to Haiku for unknown
 * formats), feed the resulting stubs through the same dedupe/create
 * logic every other intake driver uses, then relabel. A message that
 * fails to parse is left alone (not relabeled) so it's retried next
 * run rather than silently lost.
 */
final class EmailIntakeService
{
    public function __construct(
        private readonly MailSource $mailSource,
        private readonly EmailParserRegistry $registry,
        private readonly ListingIntakeService $listingIntake,
        private readonly int $userId,
    ) {}

    /**
     * @return array{processed: int, stubs: int, failed: int}
     */
    public function run(): array
    {
        $processed = 0;
        $stubCount = 0;
        $failed = 0;

        foreach ($this->mailSource->fetchUnprocessed() as $email) {
            $parser = $this->registry->resolve($email);

            if ($parser === null) {
                Log::warning('No parser (not even the fallback) claimed an email', ['subject' => $email->subject]);
                $failed++;

                continue;
            }

            try {
                $stubs = $parser->parse($email);

                foreach ($stubs as $stub) {
                    $this->listingIntake->firstOrCreateFromStub($this->userId, $stub);
                }

                $this->mailSource->markProcessed($email);
                $processed++;
                $stubCount += count($stubs);
            } catch (Throwable $e) {
                Log::error('Failed to parse an intake email', ['subject' => $email->subject, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['processed' => $processed, 'stubs' => $stubCount, 'failed' => $failed];
    }
}
