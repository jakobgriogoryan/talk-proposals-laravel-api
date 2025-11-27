<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a proposal file is not found.
 */
class ProposalFileNotFoundException extends ProposalException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Proposal file not found')
    {
        parent::__construct($message, Response::HTTP_NOT_FOUND);
    }
}
