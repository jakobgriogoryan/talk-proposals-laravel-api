<?php

declare(strict_types=1);

namespace App\Search;

use Elastic\Adapter\Search\SearchParameters;
use Elastic\ScoutDriver\Factories\SearchParametersFactory;
use Laravel\Scout\Builder;

class ProposalSearchParametersFactory extends SearchParametersFactory
{
    public function makeFromBuilder(Builder $builder, array $options = []): SearchParameters
    {
        return parent::makeFromBuilder($builder, $options)->trackTotalHits(true)->source(false);
    }

    protected function makeQuery(Builder $builder): array
    {
        $query = parent::makeQuery($builder);
        if (trim($builder->query) !== '') {
            // Treat user input as text, never Lucene syntax or field selectors.
            $query['bool']['must'] = ['multi_match' => [
                'query' => $builder->query,
                'fields' => ['title^3', 'description', 'tags', 'user_name'],
                'type' => 'best_fields',
            ]];
        }

        return $query;
    }
}
