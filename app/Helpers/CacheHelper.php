<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Constants\PaginationConstants;
use Illuminate\Support\Facades\Cache;

/**
 * Helper class for structured cache key management and operations.
 */
final class CacheHelper
{
    /**
     * Cache key prefixes.
     */
    private const PREFIX_TAGS = 'tags';

    private const PREFIX_TOP_RATED = 'top_rated_proposals';

    private const PREFIX_USER = 'user';

    private const PREFIX_PROPOSAL = 'proposal';

    /**
     * Cache TTL in seconds.
     */
    private const TTL_TAGS = 3600; // 1 hour

    private const TTL_TOP_RATED = 900; // 15 minutes

    private const TTL_USER = 300; // 5 minutes

    /**
     * Generate cache key for tags list.
     */
    public static function tagsKey(?string $search = null, int $page = 1, int $perPage = PaginationConstants::DEFAULT_TAGS_PER_PAGE): string
    {
        $key = self::PREFIX_TAGS;
        if ($search !== null) {
            $key .= ':search:'.md5($search);
        }

        return $key.':page:'.$page.':per_page:'.$perPage.':version:'.Cache::get('tags:version', '0');
    }

    /**
     * Generate cache key for top-rated proposals.
     */
    public static function topRatedKey(int $limit = 10): string
    {
        return self::PREFIX_TOP_RATED.':limit:'.$limit;
    }

    /**
     * Generate cache key for user data.
     */
    public static function userKey(int $userId): string
    {
        return self::PREFIX_USER.':'.$userId;
    }

    /**
     * Generate cache key for proposal.
     */
    public static function proposalKey(int $proposalId): string
    {
        return self::PREFIX_PROPOSAL.':'.$proposalId;
    }

    /**
     * Get tags from cache or execute callback and cache result.
     */
    public static function rememberTags(callable $callback, ?string $search = null, int $page = 1, int $perPage = PaginationConstants::DEFAULT_TAGS_PER_PAGE): mixed
    {
        return Cache::remember(
            self::tagsKey($search, $page, $perPage),
            self::TTL_TAGS,
            $callback
        );
    }

    /**
     * Get top-rated proposals from cache or execute callback and cache result.
     */
    public static function rememberTopRated(callable $callback, int $limit = 10): mixed
    {
        return Cache::remember(
            self::topRatedKey($limit),
            self::TTL_TOP_RATED,
            $callback
        );
    }

    /**
     * Get user data from cache or execute callback and cache result.
     */
    public static function rememberUser(callable $callback, int $userId): mixed
    {
        return Cache::remember(
            self::userKey($userId),
            self::TTL_USER,
            $callback
        );
    }

    /**
     * Invalidate tags cache.
     */
    public static function forgetTags(?string $search = null): void
    {
        Cache::forget(self::tagsKey($search));
        // Rotate the namespace on every store, including drivers without cache tags.
        // Unreachable pages expire naturally at TTL_TAGS; no wildcard scan is needed.
        Cache::forever('tags:version', bin2hex(random_bytes(16)));
    }

    /**
     * Invalidate top-rated proposals cache.
     */
    public static function forgetTopRated(?int $limit = null): void
    {
        if ($limit !== null) {
            Cache::forget(self::topRatedKey($limit));

            return;
        }

        Cache::deleteMultiple(array_map(self::topRatedKey(...), range(1, PaginationConstants::MAX_TOP_RATED_LIMIT)));
    }

    /**
     * Invalidate user cache.
     */
    public static function forgetUser(int $userId): void
    {
        Cache::forget(self::userKey($userId));
    }

    /**
     * Invalidate proposal cache.
     */
    public static function forgetProposal(int $proposalId): void
    {
        Cache::forget(self::proposalKey($proposalId));
    }

    /**
     * Invalidate all proposal-related caches.
     *
     * @throws \Exception When the configured cache backend fails; database writes may already be committed.
     */
    public static function forgetProposalRelated(int $proposalId): void
    {
        self::forgetProposal($proposalId);
        // Invalidate top-rated cache when a proposal changes
        self::forgetTopRated();
    }

    /**
     * Invalidate all caches related to a user.
     */
    public static function forgetUserRelated(int $userId): void
    {
        self::forgetUser($userId);
        // Invalidate top-rated cache as user's proposals might affect rankings
        self::forgetTopRated();
    }
}
