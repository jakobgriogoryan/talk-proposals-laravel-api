<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SearchSetupException;
use App\Search\ElasticsearchProposalIndex;
use Illuminate\Console\Command;
use Throwable;

class SetupElasticsearch extends Command
{
    protected $signature = 'scout:setup-elastic';

    protected $description = 'Create the proposal Elasticsearch index with explicit mappings, without replacing existing data';

    public function handle(ElasticsearchProposalIndex $index): int
    {
        try {
            $index->setup();
        } catch (Throwable $exception) {
            $this->error($exception instanceof SearchSetupException ? $exception->getMessage()
                : 'Elasticsearch setup failed. Check connectivity, credentials, TLS and index-management permissions.');

            return self::FAILURE;
        }
        $this->info('Proposal Elasticsearch index mappings are ready. No existing index was replaced.');

        return self::SUCCESS;
    }
}
