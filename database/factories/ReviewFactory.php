<?php

namespace Database\Factories;

use App\Enums\ReviewRating;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $validRatings = ReviewRating::values();
        
        return [
            'proposal_id' => Proposal::factory(),
            'reviewer_id' => User::factory()->state(['role' => 'reviewer']),
            'rating' => fake()->randomElement($validRatings),
            'comment' => fake()->optional(0.7)->paragraph(), // 70% chance of having a comment
        ];
    }
}
