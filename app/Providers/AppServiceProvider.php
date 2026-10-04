<?php

declare(strict_types=1);

namespace App\Providers;

use App\Helpers\AlgoliaConfiguration;
use App\Models\Proposal;
use App\Models\Review;
use App\Policies\ProposalPolicy;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Application service provider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Proposal::class => ProposalPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Copied example credentials must not cause requests to invalid hosts.
        if (config('scout.driver') === 'algolia' && ! AlgoliaConfiguration::isConfigured()) {
            Config::set('scout.driver', 'collection');
        }
    }
}
