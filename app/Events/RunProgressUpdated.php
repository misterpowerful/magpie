<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Spec sections 3/10: "Reverb (websockets for live dashboard and run
 * progress)". Broadcast synchronously (ShouldBroadcastNow, not
 * ShouldBroadcast) because the run orchestrator is itself a long-lived
 * CLI process, not a web request — queuing this would need a separate
 * queue worker just to deliver a progress update that's only useful
 * immediately.
 */
final class RunProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $stageStats
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $runId,
        public readonly string $status,
        public readonly array $stageStats,
        public readonly int $backlog,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("runs.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'run.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run_id' => $this->runId,
            'status' => $this->status,
            'stage_stats' => $this->stageStats,
            'backlog' => $this->backlog,
        ];
    }
}
