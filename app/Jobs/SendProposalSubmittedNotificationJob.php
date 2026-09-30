<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProposalSubmittedNotification;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send proposal submitted notification email.
 */
class SendProposalSubmittedNotificationJob implements ShouldQueue
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
        public Proposal $proposal
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all admin users
            $admins = User::where('role', 'admin')->get();

            if ($admins->isEmpty()) {
                Log::warning('No admin users found to notify about proposal submission', [
                    'proposal_id' => $this->proposal->id,
                ]);
                return;
            }

            // Send notification to each admin
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(
                    new ProposalSubmittedNotification($this->proposal, $admin)
                );
            }

            Log::info('Proposal submitted notifications sent', [
                'proposal_id' => $this->proposal->id,
                'admin_count' => $admins->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send proposal submitted notification', [
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
        Log::error('SendProposalSubmittedNotificationJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

