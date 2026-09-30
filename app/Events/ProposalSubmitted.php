<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Proposal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Proposal $proposal,
        public ?string $filePath = null,
        public ?int $userId = null
    ) {
        //
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
