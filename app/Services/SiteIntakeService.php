<?php

declare(strict_types=1);

namespace App\Services;

use App\Intake\IntakeDriverRegistry;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one site's intake driver and feeds every stub it returns
 * through the dedupe/create logic (ListingIntakeService), recording
 * the outcome on the site itself so the dashboard/admin panel can show
 * per-site status (spec sections 5 and 10).
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

            $site->update(['last_run_at' => Carbon::now(), 'last_status' => 'ok']);

            return count($stubs);
        } catch (Throwable $e) {
            Log::error('Site intake failed', ['site_id' => $site->id, 'site' => $site->name, 'error' => $e->getMessage()]);

            $site->update(['last_run_at' => Carbon::now(), 'last_status' => 'error']);

            return 0;
        }
    }
}
