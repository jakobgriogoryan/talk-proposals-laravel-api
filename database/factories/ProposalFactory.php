<?php

namespace Database\Factories;

use App\Constants\FileConstants;
use App\Models\Proposal;
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
            'file_path' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    /** Give demo proposals separate real attachments, not nonexistent paths. */
    public function withSampleAttachment(): static
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Sample proposal attachments are only available in local and testing environments.');
        }

        return $this->state(fn () => [
            'file_path' => FileConstants::PROPOSAL_STORAGE_PATH.'/'.fake()->uuid().'.pdf',
        ])->afterCreating(function (Proposal $proposal): void {
            if ($proposal->file_path !== null) {
                SampleProposalAttachment::store($proposal->file_path);
            }
        });
    }
}
