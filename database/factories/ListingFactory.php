<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();
        $title = fake()->jobTitle();

        return [
            'user_id' => User::factory(),
            'canonical_url' => 'https://example.com/jobs/'.fake()->unique()->uuid(),
            'company' => $company,
            'title' => $title,
            'location_text' => fake()->city(),
            'remote_type' => fake()->randomElement(['remote', 'onsite', 'hybrid', null]),
            'raw_text' => fake()->paragraphs(3, true),
            'content_hash' => hash('sha256', strtolower(trim($company)).'|'.strtolower(trim($title))),
            'state' => 'new',
            'status' => 'new',
            'first_seen_at' => now(),
        ];
    }
}
