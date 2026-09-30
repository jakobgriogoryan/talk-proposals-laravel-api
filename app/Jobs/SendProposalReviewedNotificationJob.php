<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProposalReviewedNotification;
use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send proposal reviewed notification email.
 */
class SendProposalReviewedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Proposal $proposal,
        public Review $review
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the proposal speaker
            $speaker = $this->proposal->user;

            if (!$speaker) {
                Log::warning('Proposal has no speaker to notify about review', [
                    'proposal_id' => $this->proposal->id,
                ]);
                return;
            }

            // Send notification to speaker
            Mail::to($speaker->email)->send(
                new ProposalReviewedNotification(
                    $this->proposal,
                    $this->review,
                    $speaker
                )
            );

            Log::info('Proposal reviewed notification sent', [
                'proposal_id' => $this->proposal->id,
                'review_id' => $this->review->id,
                'speaker_email' => $speaker->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send proposal reviewed notification', [
                'proposal_id' => $this->proposal->id,
                'review_id' => $this->review->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendProposalReviewedNotificationJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'review_id' => $this->review->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

