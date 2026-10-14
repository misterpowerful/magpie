<?php

declare(strict_types=1);

namespace App\Services;

use App\Intake\IntakeDriver;
use App\Intake\IntakeDriverRegistry;
use App\Models\Site;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runs site intake drivers and feeds every stub they return through the
 * dedupe/create logic (ListingIntakeService), recording the outcome on
 * each site itself so the dashboard/admin panel can show per-site
 * status (spec sections 5 and 10).
 */
final class SiteIntakeService
{
    public function __construct(
        private readonly IntakeDriverRegistry $registry,
        private readonly ListingIntakeService $listingIntake,
    ) {}

    /**
     * @return int number of stubs processed (new or already-existing)
     */
    public function run(Site $site): int
    {
        try {
            $driver = $this->registry->resolve($site);
            $stubs = $driver->fetch($site);

            foreach ($stubs as $stub) {
                $this->listingIntake->firstOrCreateFromStub($site->user_id, $stub);
            }

            $this->markOk($site);

            return count($stubs);
        } catch (Throwable $e) {
            $this->markFailed($site, $e);

            return 0;
        }
    }

    /**
     * Fetches every given site concurrently via Http::pool() (spec
     * section 8: pooled intake with a per-site timeout, one site's
     * failure never blocking another's) and feeds each site's stubs
     * through the same dedupe/create logic as run(). Used by the run
     * orchestrator's intake stage; run() (above) stays for the single-
     * site "run now" admin action, where pooling buys nothing.
     *
     * @param  Collection<int, Site>  $sites
     * @return array<int, int> site id => stub count processed
     */
    public function runMany(Collection $sites): array
    {
        $results = [];
        $drivers = [];

        foreach ($sites as $site) {
            try {
                $drivers[$site->id] = $this->registry->resolve($site);
            } catch (Throwable $e) {
                $this->markFailed($site, $e);
                $results[$site->id] = 0;
            }
        }

        $poolable = $sites->filter(fn (Site $site) => isset($drivers[$site->id]));

        if ($poolable->isEmpty()) {
            return $results;
        }

        $timeout = (int) config('magpie.run.intake_timeout_seconds');

        // Pool::as() keys are plain PHP array keys, so a numeric-looking
        // key like (string) $site->id is auto-cast back to an int by PHP
        // itself — the array Http::pool() returns is really keyed by int.
        /** @var array<int, Response|Throwable> $responses */
        $responses = Http::pool(function (Pool $pool) use ($poolable, $drivers, $timeout) {
            foreach ($poolable as $site) {
                $pool->as((string) $site->id)
                    ->withHeaders($drivers[$site->id]->headers())
                    ->timeout($timeout)
                    ->get($site->url);
            }
        });

        foreach ($poolable as $site) {
            $response = $responses[$site->id] ?? throw new RuntimeException("Pooled fetch produced no response for site {$site->id}");
            $results[$site->id] = $this->processPooledResponse($site, $drivers[$site->id], $response);
        }

        return $results;
    }

    private function processPooledResponse(Site $site, IntakeDriver $driver, Response|Throwable $response): int
    {
        try {
            if ($response instanceof Throwable) {
                throw $response;
            }

            $response->throw();

            $stubs = $driver->parse($site, $response);

            foreach ($stubs as $stub) {
                $this->listingIntake->firstOrCreateFromStub($site->user_id, $stub);
            }

            $this->markOk($site);

            return count($stubs);
        } catch (Throwable $e) {
            $this->markFailed($site, $e);

            return 0;
        }
    }

    private function markOk(Site $site): void
    {
        $site->update(['last_run_at' => Carbon::now(), 'last_status' => 'ok']);
    }

    private function markFailed(Site $site, Throwable $e): void
    {
        Log::error('Site intake failed', ['site_id' => $site->id, 'site' => $site->name, 'error' => $e->getMessage()]);

        $site->update(['last_run_at' => Carbon::now(), 'last_status' => 'error']);
    }
}
