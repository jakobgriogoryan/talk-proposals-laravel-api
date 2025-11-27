<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'speaker']),
            'title' => fake()->sentence(),
            'description' => fake()->paragraphs(3, true),
            'file_path' => 'proposals/'.fake()->uuid().'.pdf',
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
        ];
    }
}
