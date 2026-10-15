<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Notifier;
use App\Inbox\ApplicationConfirmationMatcher;
use App\Inbox\MailSource;
use App\Models\Listing;
use App\Models\ProcessedConfirmationEmail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Spec section 9's "deterministic confirmation matcher" alternative to
 * Gatsby: scans the owner's normal mail (not the job-alert inbox_label)
 * for "thank you for applying" style emails and matches them to open
 * listings by company name.
 *
 * Mirrors Gatsby's own rule exactly: auto-mark applied only when exactly
 * one open (new/scored, not already applied) listing fits. Several fits
 * or none are reported, never guessed. Messages are read-only (peeked,
 * never marked \Seen or moved — see ImapMailSource::fetchForConfirmationScan)
 * since the owner wants to still see these himself.
 *
 * Two independent entry points call this: the full pipeline run (as a
 * stage, after scoring) and a standalone, more-frequent scheduled
 * command (magpie:check-confirmations) — the whole point of the latter
 * is to catch a confirmation sooner than the next twice/thrice-weekly
 * pipeline sweep. The lock here, not in either caller, is what stops
 * them from ever processing the same message twice concurrently
 * (ProcessedConfirmationEmail.message_id is unique, so a genuine race
 * would otherwise surface as a constraint-violation exception).
 */
final class ApplicationConfirmationService
{
    private const MIN_COMPANY_LENGTH = 3;

    public function __construct(
        private readonly MailSource $mailSource,
        private readonly ApplicationConfirmationMatcher $matcher,
        private readonly ListingStatusService $statusService,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @return array{scanned: int, matched: int, ambiguous: int, no_match: int, not_confirmation: int, already_seen: int, locked: bool}
     */
    public function run(int $userId): array
    {
        $stats = [
            'scanned' => 0,
            'matched' => 0,
            'ambiguous' => 0,
            'no_match' => 0,
            'not_confirmation' => 0,
            'already_seen' => 0,
            'locked' => false,
        ];

        $lock = Cache::lock('magpie:confirmation-check-lock', (int) config('magpie.mail.confirmation_lock_seconds'));

        if (! $lock->get()) {
            $stats['locked'] = true;

            return $stats;
        }

        try {
            return $this->scan($userId, $stats);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, int|bool>  $stats
     * @return array<string, int|bool>
     */
    private function scan(int $userId, array $stats): array
    {
        $since = Carbon::now()->subDays((int) config('magpie.mail.confirmation_lookback_days'));

        foreach ($this->mailSource->fetchForConfirmationScan($since) as $email) {
            $stats['scanned']++;

            if (ProcessedConfirmationEmail::where('message_id', $email->messageId)->exists()) {
                $stats['already_seen']++;

                continue;
            }

            $bodyText = $email->bodyText ?? strip_tags((string) $email->bodyHtml);

            if (! $this->matcher->looksLikeConfirmation($email->subject, $bodyText)) {
                $stats['not_confirmation']++;

                continue;
            }

            $candidates = $this->matcher->extractCompanyCandidates($email->subject, $bodyText, $email->from);
            $matches = $this->findMatchingListings($userId, $candidates);

            if ($matches->count() === 1) {
                /** @var Listing $listing */
                $listing = $matches->first();

                $this->statusService->setStatus(
                    $listing,
                    'applied',
                    'email',
                    "Auto-detected from confirmation email: \"{$email->subject}\"",
                    Carbon::instance($email->receivedAt),
                );
                $this->recordOutcome($email->messageId, 'matched', $listing->id);
                $stats['matched']++;

                continue;
            }

            if ($matches->count() > 1) {
                $this->recordOutcome($email->messageId, 'ambiguous', null);
                $this->notifier->notify(
                    'Magpie: ambiguous application confirmation',
                    "\"{$email->subject}\" matched {$matches->count()} open listings — resolve manually: "
                        .$matches->pluck('title')->implode(', '),
                );
                $stats['ambiguous']++;

                continue;
            }

            $this->recordOutcome($email->messageId, 'no_match', null);
            $this->notifier->notify(
                'Magpie: unmatched application confirmation',
                "\"{$email->subject}\" looked like an application confirmation but matched no open listing.",
            );
            $stats['no_match']++;
        }

        return $stats;
    }

    /**
     * @param  array<int, string>  $candidates
     * @return Collection<int, Listing>
     */
    private function findMatchingListings(int $userId, array $candidates): Collection
    {
        $normalizedCandidates = array_filter(
            array_map($this->matcher->normalizeCompany(...), $candidates),
            fn (string $c) => strlen($c) >= self::MIN_COMPANY_LENGTH,
        );

        if ($normalizedCandidates === []) {
            return collect();
        }

        return Listing::where('user_id', $userId)
            ->whereIn('state', ['new', 'scored'])
            ->where('status', '!=', 'applied')
            ->whereNotNull('company')
            ->get()
            ->filter(function (Listing $listing) use ($normalizedCandidates) {
                $normalizedCompany = $this->matcher->normalizeCompany((string) $listing->company);

                if (strlen($normalizedCompany) < self::MIN_COMPANY_LENGTH) {
                    return false;
                }

                foreach ($normalizedCandidates as $candidate) {
                    if (str_contains($normalizedCompany, $candidate) || str_contains($candidate, $normalizedCompany)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function recordOutcome(string $messageId, string $outcome, ?int $listingId): void
    {
        ProcessedConfirmationEmail::create([
            'message_id' => $messageId,
            'outcome' => $outcome,
            'listing_id' => $listingId,
            'created_at' => Carbon::now(),
        ]);
    }
}
