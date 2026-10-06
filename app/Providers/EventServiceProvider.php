<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Listeners\IndexProposalOnReviewedListener;
use App\Listeners\ProcessProposalFileListener;
use App\Listeners\SendProposalReviewedNotificationListener;
use App\Listeners\SendProposalStatusChangedNotificationListener;
use App\Listeners\SendProposalSubmittedNotificationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Event service provider.
 * Registers event listeners for the application.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ProposalSubmitted::class => [
            ProcessProposalFileListener::class,
            SendProposalSubmittedNotificationListener::class,
        ],
        ProposalStatusChanged::class => [
            SendProposalStatusChangedNotificationListener::class,
        ],
        ProposalReviewed::class => [
            IndexProposalOnReviewedListener::class,
            SendProposalReviewedNotificationListener::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
