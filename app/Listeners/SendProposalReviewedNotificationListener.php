<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalReviewed;
use App\Jobs\SendProposalReviewedNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalReviewed event.
 * Dispatches notification job to send email to speaker.
 */
class SendProposalReviewedNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalReviewed $event): void
    {
        SendProposalReviewedNotificationJob::dispatch(
            $event->proposal,
            $event->review
        );
    }
}

