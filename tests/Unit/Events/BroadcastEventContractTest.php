<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Models\Proposal;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_event_identity_is_unique_and_survives_queue_serialization(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);
        $events = [
            new ProposalSubmitted($proposal), new ProposalReviewed($proposal, $review),
            new ProposalStatusChanged($proposal, 'pending', 'approved'),
            new ProposalStatusChanged($proposal, 'rejected', 'approved'),
        ];

        $ids = [];
        foreach ($events as $event) {
            $payload = $event->broadcastWith();
            $this->assertTrue(Str::isUuid($payload['event_id']));
            $restored = unserialize(serialize($event));
            $this->assertSame($payload['event_id'], $restored->broadcastWith()['event_id']);
            $this->assertSame($payload['event_id'], $event->broadcastWith()['event_id']);
            $ids[] = $payload['event_id'];
        }
        $this->assertCount(4, array_unique($ids));
    }

    public function test_legacy_queued_events_without_identity_can_still_be_broadcast(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);
        foreach ([new ProposalSubmitted($proposal), new ProposalReviewed($proposal, $review),
            new ProposalStatusChanged($proposal, 'pending', 'approved')] as $event) {
            // Simulate payloads serialized before eventId existed.
            unset($event->eventId);
            $restored = unserialize(serialize($event));
            $this->assertSame('', $restored->broadcastWith()['event_id']);
        }
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
