<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;

/**
 * Base controller with common functionality.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Log error with structured context.
     *
     * @param string $message The error message
     * @param \Exception|\Throwable $exception The exception
     * @param Request|null $request The request object
     * @param array<string, mixed> $additionalContext Additional context to include
     */
    protected function logError(
        string $message,
        \Exception|\Throwable $exception,
        ?Request $request = null,
        array $additionalContext = []
    ): void {
        $context = array_merge([
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ], $additionalContext);

        if ($request) {
            $context['request_id'] = $request->header('X-Request-ID');
            $context['user_id'] = $request->user()?->id;
            $context['method'] = $request->method();
            $context['path'] = $request->path();
            $context['ip'] = $request->ip();
        }

        Log::error($message, $context);
    }
}
