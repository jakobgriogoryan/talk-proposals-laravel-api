<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\SearchSetupException;
use App\Search\ElasticsearchProposalIndex;
use Illuminate\Console\Command;
use Throwable;

class CheckElasticsearch extends Command
{
    protected $signature = 'scout:check-elastic';

    protected $description = 'Check Elasticsearch version, proposal mappings and search without modifying data';

    public function handle(ElasticsearchProposalIndex $index): int
    {
        try {
            $index->check();
        } catch (Throwable $exception) {
            $this->error($exception instanceof SearchSetupException ? $exception->getMessage()
                : 'Elasticsearch check failed. Check connectivity, credentials, TLS, mappings and search permissions.');

            return self::FAILURE;
        }
        $this->info('Elasticsearch 8 connection, proposal mappings and search verified (read-only).');
        $this->info('This does not verify write permissions. Check queue failures after importing.');

        return self::SUCCESS;
    }
}
