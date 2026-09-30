<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalStatusChanged;
use App\Jobs\SendProposalStatusChangedNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalStatusChanged event.
 * Dispatches notification job to send email to speaker.
 */
class SendProposalStatusChangedNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalStatusChanged $event): void
    {
        SendProposalStatusChangedNotificationJob::dispatch(
            $event->proposal,
            $event->oldStatus,
            $event->newStatus
        );
    }
}

