<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Notifier;
use App\Events\RunProgressUpdated;
use App\Llm\LlmRateLimitedException;
use App\Models\Listing;
use App\Models\Preference;
use App\Models\Run;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Spec section 8 "Run behavior": strings the whole pipeline (intake,
 * stub filters, enrichment, hard filters, scoring) into one resumable
 * sweep. Resumability is state-based, not run-based — every stage only
 * picks up listings still in the state it cares about, so a run picking
 * up where a previous one left off needs no special bookkeeping beyond
 * the Listing.state values the earlier stages already set.
 *
 * Two independent time budgets: a global wall-clock cap for the whole
 * run, and a smaller per-stage cap so one slow stage can't eat the
 * entire budget. Hitting the global cap stops the run outright (status
 * paused_time); a rate limit from the LlmDriver stops it too (status
 * paused_rate_limited). Everything else about "stopping" is just not
 * doing more work this run — the next run (scheduled or catch-up) picks
 * up any listing still short of 'scored' automatically.
 */
final class RunOrchestratorService
{
    public function __construct(
        private readonly SiteIntakeService $siteIntake,
        private readonly EmailIntakeService $emailIntake,
        private readonly ListingFilterService $filterService,
        private readonly ListingEnrichmentService $enrichmentService,
        private readonly ListingScoringService $scoringService,
        private readonly ApplicationConfirmationService $confirmationService,
        private readonly Notifier $notifier,
    ) {}

    public function run(string $trigger): Run
    {
        $run = Run::create(['trigger' => $trigger, 'status' => 'running', 'started_at' => Carbon::now()]);

        // Every LlmCall recorded while this run is executing gets tagged
        // with it (CliLlmDriver reads this), so per-run cost is exact
        // rather than inferred from overlapping timestamps.
        Context::add('run_id', $run->id);

        try {
            return $this->execute($run);
        } finally {
            Context::forget('run_id');
        }
    }

    private function execute(Run $run): Run
    {
        $stats = [];

        $user = User::admin();

        if (! $user) {
            $run->update(['status' => 'failed', 'ended_at' => Carbon::now(), 'stage_stats' => ['error' => 'No admin user found']]);

            return $run;
        }

        $preferences = Preference::forUser($user);
        $globalDeadline = Carbon::now()->addMinutes((int) config('magpie.run.time_cap_minutes'));

        try {
            if ($this->withinBudget($globalDeadline)) {
                $stats['intake'] = $this->timed(fn () => $this->runIntakeStage($user));
                $this->broadcastProgress($run, $user, 'running', $stats);
            }

            if ($this->withinBudget($globalDeadline)) {
                $stats['stub_filter'] = $this->timed(fn () => $this->runStubFilterStage($user, $preferences, $this->stageDeadline($globalDeadline)));
                $this->broadcastProgress($run, $user, 'running', $stats);
            }

            if ($this->withinBudget($globalDeadline)) {
                $stats['enrichment'] = $this->timed(fn () => $this->runEnrichmentStage($user, $this->stageDeadline($globalDeadline)));
                $this->broadcastProgress($run, $user, 'running', $stats);
            }

            if ($this->withinBudget($globalDeadline)) {
                $stats['hard_filter'] = $this->timed(fn () => $this->runHardFilterStage($user, $preferences, $this->stageDeadline($globalDeadline)));
                $this->broadcastProgress($run, $user, 'running', $stats);
            }

            if ($this->withinBudget($globalDeadline)) {
                $stats['scoring'] = $this->timed(fn () => $this->runScoringStage($user, $preferences, $this->stageDeadline($globalDeadline)));
                $this->broadcastProgress($run, $user, 'running', $stats);

                if ($stats['scoring']['rate_limited'] ?? false) {
                    $run->update(['status' => 'paused_rate_limited', 'ended_at' => Carbon::now(), 'stage_stats' => $stats]);
                    $this->broadcastProgress($run, $user, 'paused_rate_limited', $stats);
                    $this->notifyOfStrongMatches($stats);

                    return $run;
                }
            }

            if ($this->withinBudget($globalDeadline)) {
                $stats['confirmation_check'] = $this->timed(fn () => $this->runConfirmationCheckStage($user));
                $this->broadcastProgress($run, $user, 'running', $stats);
            }
        } catch (Throwable $e) {
            Log::error('Run failed', ['run_id' => $run->id, 'error' => $e->getMessage()]);
            $run->update(['status' => 'failed', 'ended_at' => Carbon::now(), 'stage_stats' => $stats]);
            $this->broadcastProgress($run, $user, 'failed', $stats);

            return $run;
        }

        $status = $this->withinBudget($globalDeadline) ? 'done' : 'paused_time';

        $run->update(['status' => $status, 'ended_at' => Carbon::now(), 'stage_stats' => $stats]);
        $this->broadcastProgress($run, $user, $status, $stats);
        $this->notifyOfStrongMatches($stats);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function broadcastProgress(Run $run, User $user, string $status, array $stats): void
    {
        // Saved as each stage finishes (not just at the end) so a client
        // polling the run can see how far along it is.
        $run->update(['stage_stats' => $stats]);

        $backlog = Listing::where('user_id', $user->id)
            ->whereIn('state', ['new', 'fetched', 'filtered_in'])
            ->count();

        // The live dashboard update is a nice-to-have on top of the run
        // itself (spec section 9/10) — Reverb being unreachable must
        // never fail the run that's broadcasting progress about.
        try {
            event(new RunProgressUpdated($user->id, $run->id, $status, $stats, $backlog));
        } catch (Throwable $e) {
            Log::warning('Broadcasting run progress failed', ['run_id' => $run->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Spec section 9/10: "Pushover notification when a run finishes
     * with new Strong matches."
     *
     * @param  array<string, mixed>  $stats
     */
    private function notifyOfStrongMatches(array $stats): void
    {
        $strong = $stats['scoring']['strong'] ?? 0;

        if ($strong < 1) {
            return;
        }

        $this->notifier->notify(
            'Magpie: new Strong match'.($strong > 1 ? 'es' : ''),
            "{$strong} new Strong-rated listing".($strong > 1 ? 's' : '').' from this run.',
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $stage
     * @return array<string, mixed>
     */
    private function timed(callable $stage): array
    {
        $started = microtime(true);
        $result = $stage();
        $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);

        return $result;
    }

    private function withinBudget(Carbon $globalDeadline): bool
    {
        return Carbon::now()->lt($globalDeadline);
    }

    private function stageDeadline(Carbon $globalDeadline): Carbon
    {
        $stageDeadline = Carbon::now()->addMinutes((int) config('magpie.run.stage_timeout_minutes'));

        return $stageDeadline->lessThan($globalDeadline) ? $stageDeadline : $globalDeadline;
    }

    /**
     * @return array{email: mixed, sites: array<int, int>}
     */
    private function runIntakeStage(User $user): array
    {
        $emailStats = null;

        try {
            $emailStats = $this->emailIntake->run($user->id);
        } catch (Throwable $e) {
            Log::error('Email intake failed during run', ['error' => $e->getMessage()]);
            $emailStats = ['error' => $e->getMessage()];
        }

        $sites = Site::where('user_id', $user->id)
            ->where('enabled', true)
            ->where('driver', '!=', 'email')
            ->get();

        $siteStats = $sites->isEmpty() ? [] : $this->siteIntake->runMany($sites);

        return ['email' => $emailStats, 'sites' => $siteStats];
    }

    /**
     * Spec section 9's "deterministic confirmation matcher": after the
     * run has pulled in and scored this sweep's listings, check the
     * owner's mail for application-confirmation emails and auto-mark
     * the ones that match exactly one open listing. A mailbox hiccup
     * here must never fail the run the scoring stage already completed.
     *
     * @return array{scanned: int, matched: int, ambiguous: int, no_match: int, not_confirmation: int, already_seen: int}|array{error: string}
     */
    private function runConfirmationCheckStage(User $user): array
    {
        try {
            return $this->confirmationService->run($user->id);
        } catch (Throwable $e) {
            Log::error('Application-confirmation scan failed during run', ['error' => $e->getMessage()]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{processed: int, rejected: int, timed_out: bool}
     */
    private function runStubFilterStage(User $user, Preference $preferences, Carbon $deadline): array
    {
        $processed = 0;
        $rejected = 0;
        $timedOut = false;

        foreach (Listing::where('user_id', $user->id)->where('state', 'new')->cursor() as $listing) {
            if (Carbon::now()->gte($deadline)) {
                $timedOut = true;
                break;
            }

            $result = $this->filterService->runStubFilters($listing, $preferences);
            $processed++;

            if ($result->isFail()) {
                $rejected++;
            }
        }

        return ['processed' => $processed, 'rejected' => $rejected, 'timed_out' => $timedOut];
    }

    /**
     * @return array{fetched: int, needs_browser: int, enrichment_failed: int, timed_out: bool}
     */
    private function runEnrichmentStage(User $user, Carbon $deadline): array
    {
        $stats = ['fetched' => 0, 'needs_browser' => 0, 'enrichment_failed' => 0, 'timed_out' => false];

        foreach (Listing::where('user_id', $user->id)->where('state', 'new')->cursor() as $listing) {
            if (Carbon::now()->gte($deadline)) {
                $stats['timed_out'] = true;
                break;
            }

            $outcome = $this->enrichmentService->enrich($listing);
            $stats[$outcome] = ($stats[$outcome] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * @return array{filtered_in: int, filtered_out: int, timed_out: bool}
     */
    private function runHardFilterStage(User $user, Preference $preferences, Carbon $deadline): array
    {
        $stats = ['filtered_in' => 0, 'filtered_out' => 0, 'timed_out' => false];

        foreach (Listing::where('user_id', $user->id)->where('state', 'fetched')->cursor() as $listing) {
            if (Carbon::now()->gte($deadline)) {
                $stats['timed_out'] = true;
                break;
            }

            $result = $this->filterService->runHardFilters($listing, $preferences);
            $stats[$result->isFail() ? 'filtered_out' : 'filtered_in']++;
        }

        return $stats;
    }

    /**
     * Survivors are scored best-candidates-first: a confirmed (high
     * confidence) salary parse ranks ahead of an unparsed/unknown one,
     * so if the run is cut short, the most promising listings were
     * scored first (spec section 8: "scoring of survivors (best
     * candidates first)").
     *
     * @return array{scored: int, strong: int, skipped: int, failed: int, timed_out: bool, rate_limited: bool}
     */
    private function runScoringStage(User $user, Preference $preferences, Carbon $deadline): array
    {
        $stats = ['scored' => 0, 'strong' => 0, 'skipped' => 0, 'failed' => 0, 'timed_out' => false, 'rate_limited' => false];

        $query = Listing::query()
            ->where('listings.user_id', $user->id)
            ->where('listings.state', 'filtered_in')
            ->leftJoin('listing_salary', 'listing_salary.listing_id', '=', 'listings.id')
            ->orderByRaw("case when listing_salary.parse_confidence = 'high' then 0 else 1 end")
            ->orderBy('listings.first_seen_at')
            ->select('listings.*');

        foreach ($query->cursor() as $listing) {
            if (Carbon::now()->gte($deadline)) {
                $stats['timed_out'] = true;
                break;
            }

            try {
                $score = $this->scoringService->score($listing, $preferences);
                $stats[$score ? 'scored' : 'skipped']++;

                if ($score && $score->rating === 'Strong') {
                    $stats['strong']++;
                }
            } catch (LlmRateLimitedException $e) {
                $stats['rate_limited'] = true;
                break;
            } catch (Throwable $e) {
                Log::error('Scoring failed for a listing', ['listing_id' => $listing->id, 'error' => $e->getMessage()]);
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
