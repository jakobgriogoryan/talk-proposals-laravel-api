<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/** Invalidation event with scalar identity; queued delivery never reloads a deleted model. */
class ReviewUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public string $eventId;

    public int $proposalId;

    public int $ownerId;

    public int $reviewId;

    public function __construct(Proposal $proposal, Review $review)
    {
        $this->eventId = (string) Str::uuid();
        $this->proposalId = $proposal->id;
        $this->ownerId = $proposal->user_id;
        $this->reviewId = $review->id;
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('proposals'),
            new PrivateChannel('proposals.'.$this->proposalId),
            new PrivateChannel('user.'.$this->ownerId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'review.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'proposal_id' => $this->proposalId,
            'review_id' => $this->reviewId,
            'message' => 'Review updated',
        ];
    }
}
