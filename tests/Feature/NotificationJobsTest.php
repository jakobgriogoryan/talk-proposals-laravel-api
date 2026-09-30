<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use App\Jobs\IndexProposalJob;
use App\Jobs\SendProposalReviewedNotificationJob;
use App\Jobs\SendProposalStatusChangedNotificationJob;
use App\Jobs\SendProposalSubmittedNotificationJob;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Test notification jobs.
 */
class NotificationJobsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test SendProposalSubmittedNotificationJob is dispatched.
     */
    public function test_proposal_submitted_notification_job_dispatched(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $proposal = Proposal::factory()->create(['user_id' => $user->id]);

        // Dispatch job
        SendProposalSubmittedNotificationJob::dispatch($proposal);

        Queue::assertPushed(SendProposalSubmittedNotificationJob::class, function ($job) use ($proposal) {
            return $job->proposal->id === $proposal->id;
        });
    }

    /**
     * Test SendProposalStatusChangedNotificationJob is dispatched.
     */
    public function test_proposal_status_changed_notification_job_dispatched(): void
    {
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create([
            'user_id' => $user->id,
            'status' => ProposalStatus::PENDING->value,
        ]);

        // Dispatch job
        SendProposalStatusChangedNotificationJob::dispatch(
            $proposal,
            ProposalStatus::PENDING->value,
            ProposalStatus::APPROVED->value
        );

        Queue::assertPushed(SendProposalStatusChangedNotificationJob::class);
    }

    /**
     * Test SendProposalReviewedNotificationJob is dispatched.
     */
    public function test_proposal_reviewed_notification_job_dispatched(): void
    {
        Queue::fake();
        Mail::fake();

        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        $review = Review::factory()->create([
            'proposal_id' => $proposal->id,
            'reviewer_id' => $reviewer->id,
        ]);

        // Dispatch job
        SendProposalReviewedNotificationJob::dispatch($proposal, $review);

        Queue::assertPushed(SendProposalReviewedNotificationJob::class);
    }

    /**
     * Test IndexProposalJob is dispatched.
     */
    public function test_index_proposal_job_dispatched(): void
    {
        Queue::fake();

        $proposal = Proposal::factory()->create();

        // Dispatch job
        IndexProposalJob::dispatch($proposal);

        Queue::assertPushed(IndexProposalJob::class);
    }
}

