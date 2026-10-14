<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Spec section 8: "7:05am catch-up job runs only if there is a
 * backlog." Backlog is any listing the pipeline hasn't finished with
 * yet (new/fetched/filtered_in) — needs_browser and enrichment_failed
 * are excluded, since those wait on the (not yet built) browser tier
 * or manual attention, not another sweep.
 */
#[Signature('magpie:catch-up')]
#[Description('Run a catch-up sweep, but only if there is a backlog')]
class CatchUpMagpie extends Command
{
    public function handle(): int
    {
        $backlog = Listing::whereIn('state', ['new', 'fetched', 'filtered_in'])->count();

        if ($backlog === 0) {
            $this->info('No backlog — skipping catch-up run.');

            return self::SUCCESS;
        }

        $this->info("Backlog of {$backlog} listing(s) found — running catch-up sweep.");

        return $this->call('magpie:run', ['--trigger' => 'catchup']);
    }
}
