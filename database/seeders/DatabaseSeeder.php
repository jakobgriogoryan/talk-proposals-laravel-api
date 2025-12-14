<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Database seeder.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user (only if doesn't exist)
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => UserRole::ADMIN->value,
                'password' => bcrypt('password'), // Default password
            ]
        );

        // Create reviewer users (only if needed)
        $existingReviewers = User::where('role', UserRole::REVIEWER->value)->count();
        $reviewersNeeded = max(0, 5 - $existingReviewers);
        $reviewers = User::where('role', UserRole::REVIEWER->value)->get();

        if ($reviewersNeeded > 0) {
            $newReviewers = User::factory()->count($reviewersNeeded)->create([
                'role' => UserRole::REVIEWER->value,
            ]);
            $reviewers = $reviewers->merge($newReviewers);
        }

        // Create speaker users (only if needed)
        $existingSpeakers = User::where('role', UserRole::SPEAKER->value)->count();
        $speakersNeeded = max(0, 5 - $existingSpeakers);
        $speakers = User::where('role', UserRole::SPEAKER->value)->get();

        if ($speakersNeeded > 0) {
            $newSpeakers = User::factory()->count($speakersNeeded)->create([
                'role' => UserRole::SPEAKER->value,
            ]);
            $speakers = $speakers->merge($newSpeakers);
        }

        // Create tags (only if needed, using firstOrCreate to avoid duplicates)
        $tags = Tag::all();
        $tagsNeeded = max(0, 10 - $tags->count());

        if ($tagsNeeded > 0) {
            $attempts = 0;
            $maxAttempts = $tagsNeeded * 3; // Allow some retries for unique constraint

            while ($tags->count() < 10 && $attempts < $maxAttempts) {
                try {
                    $newTag = Tag::factory()->create();
                    $tags->push($newTag);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Ignore duplicate errors and continue
                    if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                        throw $e;
                    }
                }
                $attempts++;
            }
        }

        // Create proposals for speakers (only if needed)
        $existingProposals = Proposal::count();
        $proposalsNeeded = max(0, 20 - $existingProposals);
        $proposals = Proposal::all();

        if ($proposalsNeeded > 0 && $speakers->isNotEmpty()) {
            $newProposals = Proposal::factory()
                ->count($proposalsNeeded)
                ->create([
                    'user_id' => fn () => $speakers->random()->id,
                ]);
            $proposals = $proposals->merge($newProposals);
        }

        // Attach tags to proposals (only if tags exist)
        if ($tags->isNotEmpty()) {
            foreach ($proposals as $proposal) {
                // Only attach if proposal doesn't already have tags
                if ($proposal->tags()->count() === 0) {
                    $proposal->tags()->attach(
                        $tags->random(rand(1, min(3, $tags->count())))->pluck('id')->toArray()
                    );
                }
            }
        }

        // Create reviews for some proposals (only if reviewers exist)
        if ($reviewers->isNotEmpty() && $proposals->isNotEmpty()) {
            foreach ($proposals->take(15) as $proposal) {
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

                // Each proposal should have at most one review per reviewer
                $reviewerSample = $availableReviewers->random(
                    rand(1, min(3, $availableReviewers->count()))
                );

                foreach ($reviewerSample as $reviewer) {
                    Review::factory()->create([
                        'proposal_id' => $proposal->id,
                        'reviewer_id' => $reviewer->id,
                    ]);
                }
            }
        }
    }
}
