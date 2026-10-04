<?php

declare(strict_types=1);

namespace App\Search;

use Elastic\Adapter\Search\SearchParameters;
use Elastic\ScoutDriver\Factories\SearchParametersFactory;
use Laravel\Scout\Builder;
use stdClass;

class ProposalSearchParametersFactory extends SearchParametersFactory
{
    public function makeFromBuilder(Builder $builder, array $options = []): SearchParameters
    {
        return parent::makeFromBuilder($builder, $options)->trackTotalHits(true)->source(false);
    }

    protected function makeQuery(Builder $builder): array
    {
        // Build plain-text queries directly, without constructing Lucene syntax.
        $must = trim($builder->query) === '' ? ['match_all' => new stdClass] : ['multi_match' => [
            'query' => $builder->query,
            'fields' => ['title^3', 'description', 'tags', 'user_name'],
            'type' => 'best_fields',
        ]];
        $query = ['bool' => ['must' => $must]];
        if ($filter = $this->makeFilter($builder)) {
            $query['bool']['filter'] = $filter;
        }

        return $query;
    }
}
