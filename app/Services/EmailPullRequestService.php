<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands an on-demand email pull from the web process to the scheduler.
 * Parsing falls back to the `claude` CLI, whose binary and login belong
 * to the CLI user — the web server user can't run it — so the request
 * only files a flag here and the every-minute scheduler (running as the
 * CLI user) performs the pull and records the outcome for the UI to poll.
 */
final class EmailPullRequestService
{
    private const KEY = 'magpie:email-pull';

    public function __construct(
        private readonly EmailIntakeService $intake,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return Cache::get(self::KEY, ['state' => 'idle']);
    }

    /**
     * @return array<string, mixed>
     */
    public function request(): array
    {
        $current = $this->status();

        if (in_array($current['state'], ['pending', 'running'], true)) {
            return $current;
        }

        $status = ['state' => 'pending', 'requested_at' => now()->toIso8601String()];
        Cache::put(self::KEY, $status, now()->addMinutes(10));

        return $status;
    }

    public function runIfRequested(int $userId): bool
    {
        $current = $this->status();

        if ($current['state'] !== 'pending') {
            return false;
        }

        Cache::put(self::KEY, ['state' => 'running', 'requested_at' => $current['requested_at']], now()->addMinutes(15));

        try {
            $result = $this->intake->run($userId);

            Cache::put(self::KEY, ['state' => 'done', ...$result, 'finished_at' => now()->toIso8601String()], now()->addHour());
        } catch (Throwable $e) {
            Log::error('On-demand email pull failed', ['error' => $e->getMessage()]);

            Cache::put(self::KEY, ['state' => 'failed', 'finished_at' => now()->toIso8601String()], now()->addHour());
        }

        return true;
    }
}
