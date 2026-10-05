<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProposalStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Older queued events may be restored without this newly added property.
    public string $eventId = '';

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Proposal $proposal,
        public string $oldStatus,
        public string $newStatus
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
            new PrivateChannel('proposals'), // Global channel for list updates
            new PrivateChannel('proposals.'.$this->proposal->id), // Specific proposal channel
            new PrivateChannel('user.'.$this->proposal->user_id), // Notify the speaker
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'proposal.status.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'proposal_id' => $this->proposal->id,
            'new_status' => $this->newStatus,
            'old_status' => $this->oldStatus,
            'proposal' => [
                'id' => $this->proposal->id,
                'title' => $this->proposal->title,
                'status' => $this->newStatus,
            ],
            'message' => "Proposal '{$this->proposal->title}' status changed from {$this->oldStatus} to {$this->newStatus}",
        ];
    }
}
