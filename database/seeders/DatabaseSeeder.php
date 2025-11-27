<?php

namespace Database\Seeders;

use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        // Create reviewer users
        $reviewers = User::factory()->count(3)->create([
            'role' => 'reviewer',
        ]);

        // Create speaker users
        $speakers = User::factory()->count(5)->create([
            'role' => 'speaker',
        ]);

        // Create tags
        $tags = Tag::factory()->count(10)->create();

        // Create proposals for speakers
        $proposals = Proposal::factory()
            ->count(20)
            ->create([
                'user_id' => fn() => $speakers->random()->id,
            ]);

        // Attach tags to proposals
        foreach ($proposals as $proposal) {
            $proposal->tags()->attach(
                $tags->random(rand(1, 3))->pluck('id')->toArray()
            );
        }

        // Create reviews for some proposals
        foreach ($proposals->take(15) as $proposal) {
            // Each proposal should have at most one review per reviewer
            $reviewerSample = $reviewers->random(
                rand(1, min(3, $reviewers->count()))
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
