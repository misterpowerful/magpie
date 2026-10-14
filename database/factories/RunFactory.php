<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Run;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Run>
 */
final class RunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trigger' => 'schedule',
            'status' => 'running',
            'started_at' => now(),
            'ended_at' => null,
            'stage_stats' => [],
        ];
    }
}
