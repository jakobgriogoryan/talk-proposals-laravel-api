<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to log requests and responses (development only).
 */
class LogRequestResponse
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log in non-production environments
        if (app()->environment('production')) {
            return $next($request);
        }

        $requestId = (string) Str::uuid();
        $startTime = microtime(true);

        // Add request ID to request for correlation
        $request->headers->set('X-Request-ID', $requestId);

        // Log request
        $this->logRequest($request, $requestId);

        $response = $next($request);

        // Log response
        $this->logResponse($request, $response, $requestId, $startTime);

        // Add request ID to response headers
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Log request details.
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ];

        // Log query parameters (sanitized)
        if ($request->query->count() > 0) {
            $context['query_params'] = $this->sanitizeParams($request->query->all());
        }

        // Log request body (sanitized, exclude sensitive fields)
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $body = $request->except(['password', 'password_confirmation', 'token']);
            if (! empty($body)) {
                $context['request_body'] = $this->sanitizeParams($body);
            }
        }

        Log::info('Incoming API Request', $context);
    }

    /**
     * Log response details.
     */
    private function logResponse(Request $request, Response $response, string $requestId, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
        ];

        // Log slow requests (>1 second)
        if ($duration > 1000) {
            Log::warning('Slow API Request', $context);
        } else {
            Log::info('API Response', $context);
        }
    }

    /**
     * Sanitize parameters by removing sensitive data.
     */
    private function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];

        foreach ($sensitiveKeys as $key) {
            if (isset($params[$key])) {
                $params[$key] = '***REDACTED***';
            }
        }

        return $params;
    }
}

