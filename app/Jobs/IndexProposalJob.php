<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to index a proposal in Algolia asynchronously.
 * 
 * This job handles Laravel Scout indexing in the background
 * to avoid blocking HTTP requests.
 */
class IndexProposalJob implements ShouldQueue
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
            // Only index if Scout is properly configured
            if (!$this->proposal->shouldBeSearchable()) {
                Log::debug('Proposal not searchable, skipping index', [
                    'proposal_id' => $this->proposal->id,
                ]);
                return;
            }

            // Refresh the proposal to ensure relationships are loaded
            $this->proposal->refresh();
            $this->proposal->loadMissing(['user', 'tags']);

            // Index the proposal using Scout
            $this->proposal->searchable();

            Log::info('Proposal indexed successfully', [
                'proposal_id' => $this->proposal->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to index proposal', [
                'proposal_id' => $this->proposal->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - indexing failures shouldn't break the application
            // The job will retry automatically
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IndexProposalJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

