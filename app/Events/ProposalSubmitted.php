<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProposalSubmitted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Older queued events may be restored without this newly added property.
    public string $eventId = '';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Proposal $proposal,
        public ?string $filePath = null,
        public ?int $userId = null
    ) {
        $this->eventId = (string) Str::uuid();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('proposals'),
            new PrivateChannel('user.'.$this->proposal->user_id), // Notify the speaker
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'proposal.submitted';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Ensure user relationship is loaded
        if (! $this->proposal->relationLoaded('user')) {
            $this->proposal->load('user');
        }

        return [
            'event_id' => $this->eventId,
            'proposal' => [
                'id' => $this->proposal->id,
                'title' => $this->proposal->title,
                'status' => $this->proposal->status,
                'user' => [
                    'id' => $this->proposal->user->id,
                    'name' => $this->proposal->user->name,
                ],
            ],
            'message' => 'New proposal submitted: '.$this->proposal->title,
        ];
    }
}
