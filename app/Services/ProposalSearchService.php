<?php

declare(strict_types=1);

namespace App\Services;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use App\Helpers\AlgoliaConfiguration;
use App\Models\Proposal;
use App\Models\User;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder as ScoutBuilder;
use Psr\Http\Client\NetworkExceptionInterface;

class ProposalSearchService
{
    private const ELASTICSEARCH_MAX_RESULT_WINDOW = 10000;

    /**
     * @param  array{search?: ?string, tags?: ?array<int, int|string>, status?: ?string, user_id?: int|string|null, page?: int|null, per_page?: int|null}  $filters  Validated request filters.
     */
    public function paginate(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        // All engines and database hydration use the same normalized values.
        $filters['tags'] = array_values(array_unique(array_map('intval', array_filter($filters['tags'] ?? []))));
        $filters['user_id'] = $user->isSpeaker() && ! $user->isAdmin() ? $user->id
            : ($user->isAdmin() && isset($filters['user_id']) ? (int) $filters['user_id'] : null);
        $driver = config('scout.driver');
        $search = $filters['search'] ?? null;
        $page = Paginator::resolveCurrentPage();
        $remote = $driver === 'elastic' || ($driver === 'algolia' && AlgoliaConfiguration::isConfigured());
        // Elasticsearch's default result window is 10,000. Deep pages use SQL.
        if ($remote && $search !== null && ($driver !== 'elastic' || $page * $perPage <= self::ELASTICSEARCH_MAX_RESULT_WINDOW)) {
            try {
                return $this->searchWithScout($user, $filters, $perPage, $page, $driver);
            } catch (AlgoliaException|ClientResponseException|ServerResponseException|NoNodeAvailableException|NetworkExceptionInterface $exception) {
                Log::warning('Remote search unavailable; using database title search', [
                    'driver' => $driver, 'exception_type' => $exception::class,
                ]);
            }
        }

        $query = $this->constrain(Proposal::query(), $user, $filters);
        if ($search !== null) {
            $query->searchByTitle($search);
        }

        return $query->latest()->paginate($perPage, ['*'], 'page', $page);
    }

    private function searchWithScout(User $user, array $filters, int $perPage, int $page, string $driver): LengthAwarePaginator
    {
        $search = Proposal::search($filters['search']);
        $terms = [];
        if ($filters['user_id'] !== null) {
            $terms['user_id'] = $filters['user_id'];
        }
        if (isset($filters['status'])) {
            $terms['status'] = $filters['status'];
        }
        if ($driver === 'elastic') {
            $this->applyElasticFilters($search, $terms, $filters['tags']);
        } else {
            $this->applyAlgoliaFilters($search, $terms, $filters['tags']);
        }

        // The database remains authoritative even if an index has stale IDs or ownership.
        $search->query(fn (Builder $query) => $this->constrain($query, $user, $filters));
        $engine = $search->model->searchableUsing();
        $results = $engine->paginate($search, $perPage, $page);
        if ($driver === 'elastic' && (($results['timed_out'] ?? false) || ($results['_shards']['failed'] ?? 0) > 0)) {
            throw new ServerResponseException('Elasticsearch returned incomplete search results.');
        }

        // Scout's paginate() with query() retrieves every matching ID to recount
        // SQL results. That breaks Elasticsearch's result window and scales poorly.
        // Use the index total and hydrate only this page with the same DB constraints.
        return new LengthAwarePaginator(
            $engine->map($search, $results, new Proposal),
            $engine->getTotalCount($results),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /**
     * @param  Builder<Proposal>  $query
     * @return Builder<Proposal>
     */
    private function constrain(Builder $query, User $user, array $filters): Builder
    {
        $query->with($user->isAdmin() ? ['user', 'tags', 'reviews'] : ['user', 'tags']);
        if ($filters['user_id'] !== null) {
            $query->byUser($filters['user_id']);
        }
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        if ($filters['tags']) {
            $query->byTags($filters['tags']);
        }

        return $query;
    }

    /**
     * @param  array<string, int|string>  $terms
     * @param  list<int>  $tags
     */
    private function applyElasticFilters(ScoutBuilder $search, array $terms, array $tags): void
    {
        foreach ($terms as $field => $value) {
            $search->where($field, $value);
        }
        if ($tags) {
            $search->whereIn('tag_ids', $tags);
        }
    }

    /**
     * @param  array<string, int|string>  $terms
     * @param  list<int>  $tags
     */
    private function applyAlgoliaFilters(ScoutBuilder $search, array $terms, array $tags): void
    {
        $clauses = [];
        foreach ($terms as $field => $value) {
            $clauses[] = $field.':'.$value;
        }
        if ($tags) {
            $clauses[] = '('.implode(' OR ', array_map(fn (int $id): string => 'tag_ids:'.$id, $tags)).')';
        }
        if ($clauses) {
            $search->options(['filters' => implode(' AND ', $clauses)]);
        }
    }
}
