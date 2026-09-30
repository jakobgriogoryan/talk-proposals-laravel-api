<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ProposalReviewed;
use App\Jobs\IndexProposalJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Listener for ProposalReviewed event.
 * Dispatches IndexProposalJob to update search index.
 */
class IndexProposalOnReviewedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ProposalReviewed $event): void
    {
        IndexProposalJob::dispatch($event->proposal);
    }
}

