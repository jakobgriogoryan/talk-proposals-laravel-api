<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a proposal is not found.
 */
class ProposalNotFoundException extends ProposalException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Proposal not found')
    {
        parent::__construct($message, Response::HTTP_NOT_FOUND);
    }
}
