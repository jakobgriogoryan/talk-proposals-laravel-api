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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProposalReviewed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Older queued events may be restored without this newly added property.
    public string $eventId = '';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Proposal $proposal,
        public Review $review
    ) {
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('proposals'),
            new PrivateChannel('proposals.'.$this->proposal->id),
            new PrivateChannel('user.'.$this->proposal->user_id), // Notify the speaker
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'proposal.reviewed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Ensure relationships are loaded
        if (! $this->review->relationLoaded('reviewer')) {
            $this->review->load('reviewer');
        }

        return [
            'event_id' => $this->eventId,
            'proposal' => [
                'id' => $this->proposal->id,
                'title' => $this->proposal->title,
            ],
            'review' => [
                'id' => $this->review->id,
                'rating' => $this->review->rating,
                'reviewer' => [
                    'id' => $this->review->reviewer->id,
                    'name' => $this->review->reviewer->name,
                ],
            ],
            'message' => 'New review added to proposal: '.$this->proposal->title,
        ];
    }
}
