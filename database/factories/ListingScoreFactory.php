<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListingScore>
 */
final class ListingScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'rating' => 'Moderate',
            'reasons' => [],
            'flags' => [],
            'commentary' => null,
            'prefs_hash' => 'test',
            'resume_hash' => 'test',
            'model' => 'claude-sonnet-5-5',
            'scored_at' => now(),
        ];
    }

    public function rating(string $rating): static
    {
        return $this->state(['rating' => $rating]);
    }
}
