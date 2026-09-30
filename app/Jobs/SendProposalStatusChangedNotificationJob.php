<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProposalStatusChangedNotification;
use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send proposal status changed notification email.
 */
class SendProposalStatusChangedNotificationJob implements ShouldQueue
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
        public string $oldStatus,
        public string $newStatus
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
                Log::warning('Proposal has no speaker to notify about status change', [
                    'proposal_id' => $this->proposal->id,
                ]);
                return;
            }

            // Send notification to speaker
            Mail::to($speaker->email)->send(
                new ProposalStatusChangedNotification(
                    $this->proposal,
                    $speaker,
                    $this->oldStatus,
                    $this->newStatus
                )
            );

            Log::info('Proposal status changed notification sent', [
                'proposal_id' => $this->proposal->id,
                'old_status' => $this->oldStatus,
                'new_status' => $this->newStatus,
                'speaker_email' => $speaker->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send proposal status changed notification', [
                'proposal_id' => $this->proposal->id,
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
        Log::error('SendProposalStatusChangedNotificationJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

