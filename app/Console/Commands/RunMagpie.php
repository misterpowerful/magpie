<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Run;
use App\Services\RunOrchestratorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('magpie:run {--trigger=schedule : schedule|catchup}')]
#[Description('Run one full pipeline sweep: intake, filters, enrichment, scoring (spec section 8 "Run behavior")')]
class RunMagpie extends Command
{
    public function handle(RunOrchestratorService $orchestrator): int
    {
        $trigger = $this->option('trigger');

        if (! in_array($trigger, Run::TRIGGERS, true)) {
            $this->error('Unknown trigger "'.$trigger.'" — expected one of: '.implode(', ', Run::TRIGGERS));

            return self::FAILURE;
        }

        $lock = Cache::lock('magpie:run-lock', (int) config('magpie.run.lock_seconds'));

        if (! $lock->get()) {
            $this->warn('Another run is already in progress — skipping.');

            return self::SUCCESS;
        }

        try {
            $run = $orchestrator->run($trigger);
        } finally {
            $lock->release();
        }

        $this->info("Run #{$run->id} finished with status: {$run->status}");

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
