<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReviewRating;
use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder for adding reviews to proposals.
 */
class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all reviewers
        $reviewers = User::where('role', UserRole::REVIEWER->value)->get();
        
        if ($reviewers->isEmpty()) {
            $this->command->warn('No reviewers found. Creating 3 reviewers...');
            $reviewers = User::factory()->count(3)->create([
                'role' => UserRole::REVIEWER->value,
            ]);
        }

        // Get all proposals
        $proposals = Proposal::all();
        
        if ($proposals->isEmpty()) {
            $this->command->warn('No proposals found. Creating 10 proposals...');
            $speakers = User::where('role', UserRole::SPEAKER->value)->get();
            
            if ($speakers->isEmpty()) {
                $speakers = User::factory()->count(5)->create([
                    'role' => UserRole::SPEAKER->value,
                ]);
            }
            
            $proposals = Proposal::factory()
                ->count(10)
                ->create([
                    'user_id' => fn () => $speakers->random()->id,
                ]);
        }

        $validRatings = ReviewRating::values();
        $reviewCount = 0;

        // Add reviews to proposals
        foreach ($proposals as $proposal) {
            // Get reviewers who haven't reviewed this proposal yet
            $existingReviewerIds = Review::where('proposal_id', $proposal->id)
                ->pluck('reviewer_id')
                ->toArray();
            
            $availableReviewers = $reviewers->reject(function ($reviewer) use ($existingReviewerIds) {
                return in_array($reviewer->id, $existingReviewerIds, true);
            });

            if ($availableReviewers->isEmpty()) {
                continue;
            }

            // Add 1-3 reviews per proposal (random)
            $numReviews = rand(1, min(3, $availableReviewers->count()));
            $selectedReviewers = $availableReviewers->random($numReviews);

            foreach ($selectedReviewers as $reviewer) {
                Review::factory()->create([
                    'proposal_id' => $proposal->id,
                    'reviewer_id' => $reviewer->id,
                    'rating' => fake()->randomElement($validRatings),
                    'comment' => fake()->optional(0.8)->paragraph(), // 80% chance of comment
                ]);
                $reviewCount++;
            }
        }

        $this->command->info("Created {$reviewCount} reviews for {$proposals->count()} proposals.");
    }
}

