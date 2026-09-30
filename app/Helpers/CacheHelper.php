<?php

declare(strict_types=1);

namespace App\Helpers;

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
    private const TTL_PROPOSAL = 1800; // 30 minutes

    /**
     * Generate cache key for tags list.
     */
    public static function tagsKey(?string $search = null): string
    {
        $key = self::PREFIX_TAGS;
        if ($search !== null) {
            $key .= ':search:'.md5($search);
        }

        return $key;
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
    public static function rememberTags(callable $callback, ?string $search = null): mixed
    {
        return Cache::remember(
            self::tagsKey($search),
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
        if ($search !== null) {
            Cache::forget(self::tagsKey($search));
        } else {
            // Invalidate all tag-related caches
            Cache::forget(self::tagsKey());
            // Note: In production with Redis, you might want to use tags for pattern-based invalidation
        }
    }

    /**
     * Invalidate top-rated proposals cache.
     */
    public static function forgetTopRated(int $limit = 10): void
    {
        Cache::forget(self::topRatedKey($limit));
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
     */
    public static function forgetProposalRelated(int $proposalId): void
    {
        self::forgetProposal($proposalId);
        // Invalidate top-rated cache when a proposal changes
        self::forgetTopRated(10);
        // You might want to invalidate other limits if they exist
    }

    /**
     * Invalidate all caches related to a user.
     */
    public static function forgetUserRelated(int $userId): void
    {
        self::forgetUser($userId);
        // Invalidate top-rated cache as user's proposals might affect rankings
        self::forgetTopRated(10);
    }
}

