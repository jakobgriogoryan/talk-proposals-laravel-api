<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Command to seed dummy data for development/testing.
 */
class SeedDummyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-dummy-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with dummy data for development and testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Seeding dummy data...');

        try {
            DB::beginTransaction();

            // Create admin user
            $admin = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make('password'),
                    'role' => UserRole::ADMIN->value,
                ]
            );

            $this->info('Created admin user: admin@example.com / password');

            // Create reviewer users
            $reviewers = User::factory()->count(3)->create([
                'role' => UserRole::REVIEWER->value,
            ]);

            $this->info('Created 3 reviewer users');

            // Create speaker users
            $speakers = User::factory()->count(5)->create([
                'role' => UserRole::SPEAKER->value,
            ]);

            $this->info('Created 5 speaker users');

            // Create tags
            $tags = Tag::factory()->count(10)->create();

            $this->info('Created 10 tags');

            // Create proposals for speakers
            $proposals = Proposal::factory()
                ->count(20)
                ->create([
                    'user_id' => fn () => $speakers->random()->id,
                ]);

            $this->info('Created 20 proposals');

            // Attach tags to proposals
            foreach ($proposals as $proposal) {
                $proposal->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')->toArray()
                );
            }

            $this->info('Attached tags to proposals');

            // Create reviews for some proposals
            $reviewedProposals = $proposals->take(15);
            foreach ($reviewedProposals as $proposal) {
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

            $this->info('Created reviews for proposals');

            DB::commit();

            $this->info('Dummy data seeded successfully!');
            $this->newLine();
            $this->info('Login credentials:');
            $this->line('  Admin: admin@example.com / password');
            $this->line('  Reviewers: Use any reviewer email with password "password"');
            $this->line('  Speakers: Use any speaker email with password "password"');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('Failed to seed dummy data: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
