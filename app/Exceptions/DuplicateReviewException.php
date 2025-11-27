<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a reviewer attempts to create a duplicate review.
 */
class DuplicateReviewException extends ReviewException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'You have already reviewed this proposal')
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
