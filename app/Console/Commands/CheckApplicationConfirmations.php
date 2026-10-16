<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ApplicationConfirmationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Standalone entry point for the application-confirmation scan (spec
 * section 9's "deterministic confirmation matcher"), decoupled from the
 * 2am Mon/Wed/Fri pipeline run so a "thank you for applying" email
 * doesn't sit unmatched until the next scheduled sweep. Safe to run far
 * more often than the full pipeline — no LLM calls, just an IMAP poll —
 * and shares a lock with the pipeline's own confirmation_check stage
 * (see ApplicationConfirmationService) so the two never race.
 */
#[Signature('magpie:check-confirmations')]
#[Description('Scan the inbox for application-confirmation emails and mark matching listings applied')]
class CheckApplicationConfirmations extends Command
{
    public function handle(ApplicationConfirmationService $service): int
    {
        $user = User::admin();

        if (! $user) {
            $this->error(User::MISSING_ADMIN_MESSAGE);

            return self::FAILURE;
        }

        $stats = $service->run($user->id);

        if ($stats['locked']) {
            $this->warn('A confirmation check (or full pipeline run) is already in progress — skipping.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Scanned %d, matched %d, ambiguous %d, no match %d, not confirmations %d, already seen %d.',
            $stats['scanned'],
            $stats['matched'],
            $stats['ambiguous'],
            $stats['no_match'],
            $stats['not_confirmation'],
            $stats['already_seen'],
        ));

        return self::SUCCESS;
    }
}
