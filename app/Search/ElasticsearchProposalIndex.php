<?php

declare(strict_types=1);

namespace App\Search;

use App\Exceptions\SearchSetupException;
use App\Models\Proposal;
use Elastic\Client\ClientBuilderInterface;

class ElasticsearchProposalIndex
{
    public const PROPERTIES = [
        'id' => ['type' => 'long'],
        'title' => ['type' => 'text'],
        'description' => ['type' => 'text'],
        'status' => ['type' => 'keyword'],
        'user_id' => ['type' => 'long'],
        'user_name' => ['type' => 'text'],
        'user_email' => ['type' => 'keyword', 'index' => false],
        'tags' => ['type' => 'text'],
        'tag_ids' => ['type' => 'long'],
        'average_rating' => ['type' => 'float'],
        'reviews_count' => ['type' => 'long'],
        'created_at' => ['type' => 'date'],
        'updated_at' => ['type' => 'date'],
    ];

    public function __construct(
        private readonly ClientBuilderInterface $builder,
        private readonly ProposalSearchParametersFactory $parametersFactory,
    ) {}

    public function name(): string
    {
        $name = (new Proposal)->searchableAs();
        if (strlen($name) > 255 || ! preg_match('/^[a-z0-9][a-z0-9_.-]*$/D', $name)) {
            throw new SearchSetupException('SCOUT_PREFIX must produce a valid lowercase Elasticsearch index name.');
        }

        return $name;
    }

    public function setup(): void
    {
        $name = $this->name();
        $client = $this->builder->default();
        $this->checkVersion($client->info()->asArray());
        if ($client->indices()->exists(['index' => $name])->asBool()) {
            $this->checkMappings($name, $client->indices()->getMapping(['index' => $name])->asArray());

            return;
        }

        $result = $client->indices()->create(['index' => $name, 'body' => [
            'mappings' => ['dynamic' => 'strict', 'properties' => self::PROPERTIES],
        ]])->asArray();
        if (! ($result['acknowledged'] ?? false)) {
            throw new SearchSetupException('Elasticsearch did not acknowledge index creation. Check the index before importing.');
        }
    }

    public function check(): void
    {
        $name = $this->name();
        $client = $this->builder->default();
        $this->checkVersion($client->info()->asArray());
        $this->checkMappings($name, $client->indices()->getMapping(['index' => $name])->asArray());
        $parameters = $this->parametersFactory->makeFromBuilder(
            Proposal::search('connection check')->where('user_id', -1)->where('status', 'pending')->whereIn('tag_ids', [-1])
        )->toArray();
        $parameters['body']['size'] = 0;
        $result = $client->search($parameters)->asArray();
        if (($result['timed_out'] ?? false) || ($result['_shards']['failed'] ?? 0) > 0) {
            throw new SearchSetupException('Elasticsearch returned incomplete search results. Check cluster health.');
        }
    }

    private function checkVersion(array $info): void
    {
        if (! str_starts_with($info['version']['number'] ?? '', '8.')) {
            throw new SearchSetupException('This integration requires Elasticsearch 8.x.');
        }
    }

    private function checkMappings(string $name, array $mappings): void
    {
        $properties = $mappings[$name]['mappings']['properties'] ?? [];
        foreach (self::PROPERTIES as $field => $definition) {
            foreach ($definition as $option => $expectedValue) {
                if (($properties[$field][$option] ?? null) !== $expectedValue) {
                    throw new SearchSetupException('Proposal index mappings are missing or incompatible. Use a new SCOUT_PREFIX, run scout:setup-elastic, then import. Existing data was not changed.');
                }
            }
        }
    }
}
