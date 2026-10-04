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
use Psr\Http\Client\NetworkExceptionInterface;

class ProposalSearchService
{
    public function paginate(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $driver = config('scout.driver');
        $search = $filters['search'] ?? null;
        $page = Paginator::resolveCurrentPage();
        $remote = $driver === 'elastic' || ($driver === 'algolia' && AlgoliaConfiguration::isConfigured());
        // Elasticsearch's default result window is 10,000. Deep pages use SQL.
        if ($remote && $search !== null && ($driver !== 'elastic' || $page * $perPage <= 10000)) {
            try {
                return $this->searchWithScout($user, $filters, $perPage, $driver);
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

        return $query->latest()->paginate($perPage);
    }

    private function searchWithScout(User $user, array $filters, int $perPage, string $driver): LengthAwarePaginator
    {
        $search = Proposal::search($filters['search']);
        $terms = [];
        if ($owner = $this->ownerId($user, $filters)) {
            $terms['user_id'] = $owner;
        }
        if (isset($filters['status'])) {
            $terms['status'] = $filters['status'];
        }
        $tags = array_values(array_map('intval', array_filter($filters['tags'] ?? [])));
        if ($driver === 'elastic') {
            foreach ($terms as $field => $value) {
                $search->where($field, $value);
            }
            if ($tags) {
                $search->whereIn('tag_ids', $tags);
            }
        } else {
            $clauses = [];
            foreach ($terms as $field => $value) {
                $clauses[] = $field.':'.$value;
            }
            if ($tags) {
                $clauses[] = '('.implode(' OR ', array_map(fn ($id) => 'tag_ids:'.$id, $tags)).')';
            }
            if ($clauses) {
                $search->options(['filters' => implode(' AND ', $clauses)]);
            }
        }

        // The database remains authoritative even if an index has stale IDs or ownership.
        $search->query(fn (Builder $query) => $this->constrain($query, $user, $filters));
        $engine = $search->model->searchableUsing();
        $page = Paginator::resolveCurrentPage();
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

    private function constrain(Builder $query, User $user, array $filters): Builder
    {
        $query->with($user->isAdmin() ? ['user', 'tags', 'reviews'] : ['user', 'tags']);
        if ($owner = $this->ownerId($user, $filters)) {
            $query->byUser($owner);
        }
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        $tags = array_values(array_map('intval', array_filter($filters['tags'] ?? [])));
        if ($tags) {
            $query->byTags($tags);
        }

        return $query;
    }

    private function ownerId(User $user, array $filters): ?int
    {
        if ($user->isSpeaker() && ! $user->isAdmin()) {
            return $user->id;
        }

        return $user->isAdmin() && isset($filters['user_id']) ? (int) $filters['user_id'] : null;
    }
}
