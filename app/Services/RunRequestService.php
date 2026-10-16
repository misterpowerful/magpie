<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Run;
use Illuminate\Support\Facades\Cache;

/**
 * Hands an on-demand pipeline run from the web process to the scheduler.
 * Scoring and email parsing shell out to the `claude` CLI, whose binary
 * and login belong to the CLI user — the web server user can't run it —
 * so the dashboard button only files a flag here and the every-minute
 * scheduler (running as the CLI user) picks it up and does the run.
 */
final class RunRequestService
{
    private const KEY = 'magpie:run-request';

    public function isPending(): bool
    {
        return Cache::has(self::KEY);
    }

    /**
     * A Run row stuck at "running" after a crash stops counting once the
     * run lock's own TTL would have expired.
     */
    public function isRunning(): bool
    {
        return Run::where('status', 'running')
            ->where('started_at', '>', now()->subSeconds((int) config('magpie.run.lock_seconds')))
            ->exists();
    }

    /**
     * @return array{pending: bool, running: bool}
     */
    public function request(): array
    {
        if (! $this->isRunning() && ! $this->isPending()) {
            Cache::put(self::KEY, now()->toIso8601String(), now()->addMinutes(10));
        }

        return ['pending' => $this->isPending(), 'running' => $this->isRunning()];
    }

    public function take(): bool
    {
        return Cache::pull(self::KEY) !== null;
    }
}
