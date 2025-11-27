<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Pagination-related constants.
 */
final class PaginationConstants
{
    /**
     * Default items per page.
     */
    public const DEFAULT_PER_PAGE = 15;

    /**
     * Minimum items per page.
     */
    public const MIN_PER_PAGE = 1;

    /**
     * Maximum items per page.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * Default limit for top-rated proposals.
     */
    public const DEFAULT_TOP_RATED_LIMIT = 10;

    /**
     * Maximum limit for top-rated proposals.
     */
    public const MAX_TOP_RATED_LIMIT = 50;

    /**
     * Minimum average rating for top-rated proposals.
     */
    public const MIN_TOP_RATED_RATING = 4.0;
}
