<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // statefulApi() applies EnsureFrontendRequestsAreStateful which enables sessions
        // for requests from stateful domains, but StartSession must run first
        $middleware->statefulApi();

        // Add StartSession middleware to API routes so sessions can be used
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // Sanctum handles CSRF for SPA automatically
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
