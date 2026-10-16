<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RunRequestService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('magpie:run-requested')]
#[Description('Run a full pipeline sweep if the dashboard button has requested one')]
class RunRequestedPipeline extends Command
{
    public function handle(RunRequestService $requests): int
    {
        if (! $requests->take()) {
            return self::SUCCESS;
        }

        return $this->call('magpie:run', ['--trigger' => 'manual']);
    }
}
