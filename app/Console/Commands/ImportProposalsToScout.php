<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\AlgoliaConfiguration;
use App\Models\Proposal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportProposalsToScout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:import-proposals
                            {--chunk=500 : Number of proposals to import per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue existing proposals into the active Algolia or Elasticsearch index';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $driver = config('scout.driver');

        if (! in_array($driver, ['algolia', 'elastic'], true)) {
            $this->error('A remote search engine is not active. Configure Algolia or Elasticsearch first.');
            $this->info('Set SCOUT_DRIVER=algolia or SCOUT_DRIVER=elastic in .env after configuring the engine.');

            return Command::FAILURE;
        }

        if ($driver === 'algolia' && ! AlgoliaConfiguration::isConfigured()) {
            $this->error('Algolia credentials are missing, placeholders, or malformed.');
            $this->info('Please set ALGOLIA_APP_ID and ALGOLIA_SECRET in your .env file.');

            return Command::FAILURE;
        }

        if (! config('scout.queue') || config('queue.default') === 'sync') {
            $this->error('Use SCOUT_QUEUE=true and an asynchronous queue connection for remote search.');

            return Command::FAILURE;
        }

        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($chunkSize === false) {
            $this->error('Chunk size must be a positive integer.');

            return Command::FAILURE;
        }

        $this->info("Queueing proposal indexing for {$driver}...");

        $total = Proposal::count();
        $queued = 0;
        $failed = 0;

        $this->info("Found {$total} proposals to import.");

        Proposal::with(['user', 'tags'])
            ->chunk($chunkSize, function ($proposals) use (&$queued, &$failed, $total) {
                foreach ($proposals as $proposal) {
                    try {
                        $proposal->searchable();
                        $queued++;

                        if ($queued % 50 === 0) {
                            $this->info("Queued {$queued}/{$total} proposals...");
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $this->warn("Failed to queue proposal ID {$proposal->id}.");
                        Log::error('Failed to import proposal to Scout', [
                            'proposal_id' => $proposal->id,
                            'exception_type' => $e::class,
                        ]);
                    }
                }
            });

        $this->info("Queued {$queued}/{$total} proposals for indexing. Keep the queue worker running and check failed jobs.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
