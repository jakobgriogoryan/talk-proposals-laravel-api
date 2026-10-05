<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Trait for creating the application in tests.
 */
trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Cached development configuration bypasses PHPUnit's isolated env.
        // Refuse to bootstrap before any provider or migration can touch data.
        if ($app->configurationIsCached()) {
            throw new \LogicException('Tests refuse cached application configuration. Run php artisan config:clear first.');
        }
        $app->booting(function () use ($app): void {
            $config = $app->make('config');
            if ($config->get('app.env') !== 'testing'
                || $config->get('database.default') !== 'sqlite'
                || $config->get('database.connections.sqlite.database') !== ':memory:') {
                throw new \LogicException('Tests require APP_ENV=testing and an isolated SQLite :memory: database.');
            }
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
