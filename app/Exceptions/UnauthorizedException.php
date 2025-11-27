<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a user is unauthorized to perform an action.
 */
class UnauthorizedException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = 'Unauthorized', int $code = Response::HTTP_FORBIDDEN)
    {
        parent::__construct($message, $code);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
