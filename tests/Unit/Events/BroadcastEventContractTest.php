<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastEventContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_event_contract(): void
    {
        $proposal = Proposal::factory()->create();
        $event = new ProposalSubmitted($proposal);

        $this->assertSame('proposal.submitted', $event->broadcastAs());
        $this->assertSame([
            'private-proposals',
            "private-user.{$proposal->user_id}",
        ], $this->channelNames($event->broadcastOn()));
    }

    public function test_review_event_contract(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);
        $event = new ProposalReviewed($proposal, $review);

        $this->assertSame('proposal.reviewed', $event->broadcastAs());
        $this->assertSame([
            'private-proposals',
            "private-proposals.{$proposal->id}",
            "private-user.{$proposal->user_id}",
        ], $this->channelNames($event->broadcastOn()));
    }

    public function test_status_event_contract_uses_minimal_payload(): void
    {
        $proposal = Proposal::factory()->create();
        $event = new ProposalStatusChanged($proposal, 'pending', 'approved');

        $this->assertSame('proposal.status.changed', $event->broadcastAs());
        $this->assertSame([
            'private-proposals',
            "private-proposals.{$proposal->id}",
            "private-user.{$proposal->user_id}",
        ], $this->channelNames($event->broadcastOn()));

        $payload = $event->broadcastWith();

        $this->assertSame($proposal->id, $payload['proposal']['id']);
        $this->assertSame($proposal->title, $payload['proposal']['title']);
        $this->assertArrayNotHasKey('description', $payload['proposal']);
        $this->assertArrayNotHasKey('file_path', $payload['proposal']);
    }

    /**
     * @param  array<int, \Illuminate\Broadcasting\Channel>  $channels
     * @return array<int, string>
     */
    private function channelNames(array $channels): array
    {
        return array_map(static fn ($channel): string => $channel->name, $channels);
    }
}
