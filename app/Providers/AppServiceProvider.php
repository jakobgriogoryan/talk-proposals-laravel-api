<?php

declare(strict_types=1);

namespace App\Providers;

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

        // Auto-fallback Scout driver if Algolia is selected but credentials are missing
        $scoutDriver = config('scout.driver', 'collection');
        if ($scoutDriver === 'algolia') {
            $appId = config('scout.algolia.id', '');
            $secret = config('scout.algolia.secret', '');

            if (empty($appId) || empty($secret)) {
                // Fallback to collection driver if Algolia credentials are missing
                Config::set('scout.driver', 'collection');
            }
        }
    }
}
