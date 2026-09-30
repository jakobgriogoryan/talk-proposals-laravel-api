<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalSubmitted;
use App\Jobs\IndexProposalJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalSubmitted event.
 * Dispatches IndexProposalJob to update search index.
 */
class IndexProposalOnSubmittedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalSubmitted $event): void
    {
        IndexProposalJob::dispatch($event->proposal);
    }
}

