<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalStatusChanged;
use App\Jobs\IndexProposalJob;
/**
 * Listener for ProposalStatusChanged event.
 * Dispatches IndexProposalJob to update search index.
 */
class IndexProposalOnStatusChangedListener
{

    /**
     * Handle the event.
     */
    public function handle(ProposalStatusChanged $event): void
    {
        IndexProposalJob::dispatch($event->proposal);
    }
}

