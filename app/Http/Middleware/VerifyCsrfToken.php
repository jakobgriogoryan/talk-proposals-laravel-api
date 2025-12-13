<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // API routes are handled by Sanctum's EnsureFrontendRequestsAreStateful middleware
        'api/*',
    ];

    /**
     * Determine if the request should be excluded from CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request): bool
    {
        // Exclude all API routes - Sanctum handles CSRF for stateful requests
        $path = $request->path();
        if (str_starts_with($path, 'api/')) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
