<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalSubmitted;
use App\Jobs\SendProposalSubmittedNotificationJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalSubmitted event.
 * Dispatches notification job to send email to admins.
 */
class SendProposalSubmittedNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalSubmitted $event): void
    {
        SendProposalSubmittedNotificationJob::dispatch($event->proposal);
    }
}

