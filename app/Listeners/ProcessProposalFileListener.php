<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalSubmitted;
use App\Jobs\ProcessProposalFileJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalSubmitted event.
 * Dispatches ProcessProposalFileJob if a file was uploaded.
 */
class ProcessProposalFileListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalSubmitted $event): void
    {
        // Only process file if filePath and userId are provided
        if ($event->filePath && $event->userId) {
            ProcessProposalFileJob::dispatch(
                $event->proposal,
                $event->filePath,
                $event->userId
            );
        }
    }
}

