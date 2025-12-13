<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Route service provider for rate limiting configuration.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Configure the rate limiters for the application.
     */
    public function boot(): void
    {
        // Rate limiter for authentication endpoints (login/register)
        // Limits: 5 attempts per minute per IP address
        RateLimiter::for('auth', function (Request $request) {
            $maxAttempts = (int) config('app.rate_limit.auth_attempts', 5);
            $decayMinutes = (int) config('app.rate_limit.auth_decay_minutes', 1);

            return Limit::perMinute($maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Too many authentication attempts. Please try again later.',
                    ], 429, $headers);
                });
        });

        // Rate limiter for proposal creation
        // Limits: 10 proposals per hour per authenticated user
        RateLimiter::for('proposals', function (Request $request) {
            $maxAttempts = (int) config('app.rate_limit.proposals_per_hour', 10);
            $decayMinutes = (int) config('app.rate_limit.proposals_decay_minutes', 60);

            $key = $request->user() 
                ? 'proposals:user:'.$request->user()->id 
                : 'proposals:ip:'.$request->ip();

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Too many proposal submissions. Please try again later.',
                    ], 429, $headers);
                });
        });

        // Rate limiter for file uploads (DoS protection)
        // Limits: 20 uploads per hour per authenticated user, 5 per hour per IP
        RateLimiter::for('uploads', function (Request $request) {
            $maxAttempts = $request->user() 
                ? (int) config('app.rate_limit.uploads_per_hour_user', 20)
                : (int) config('app.rate_limit.uploads_per_hour_ip', 5);
            
            $decayMinutes = (int) config('app.rate_limit.uploads_decay_minutes', 60);

            $key = $request->user() 
                ? 'uploads:user:'.$request->user()->id 
                : 'uploads:ip:'.$request->ip();

            return Limit::perMinutes($decayMinutes, $maxAttempts)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Too many file uploads. Please try again later.',
                    ], 429, $headers);
                });
        });
    }
}

