<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One execution of the full pipeline sweep (spec section 4 "runs" /
 * section 8 "Run behavior"). Resumability is state-based, not
 * run-based — a Run row is a record of what happened, not something
 * individual listings belong to.
 */
#[Fillable(['trigger', 'status', 'started_at', 'ended_at', 'stage_stats'])]
class Run extends Model
{
    /** @use HasFactory<RunFactory> */
    use HasFactory;

    public const TRIGGERS = ['schedule', 'catchup'];

    public const STATUSES = ['running', 'done', 'paused_time', 'paused_rate_limited', 'failed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'stage_stats' => 'array',
        ];
    }
}
