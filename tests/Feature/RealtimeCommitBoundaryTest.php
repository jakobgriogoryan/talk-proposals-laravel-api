<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RealtimeCommitBoundaryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    public function test_domain_events_wait_for_outer_commit_and_are_discarded_on_rollback(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);
        $events = [new ProposalSubmitted($proposal), new ProposalReviewed($proposal, $review),
            new ProposalStatusChanged($proposal, 'pending', 'approved')];
        $observed = [];
        foreach ($events as $event) {
            Event::listen($event::class, function () use (&$observed): void { $observed[] = DB::transactionLevel(); });
        }
        DB::beginTransaction();
        DB::beginTransaction();
        foreach ($events as $event) event($event);
        DB::commit();
        $this->assertSame([], $observed);
        DB::rollBack();
        $this->assertSame([], $observed);
        DB::beginTransaction();
        DB::beginTransaction();
        foreach ($events as $event) event($event);
        DB::commit();
        $this->assertSame([], $observed);
        DB::commit();
        $this->assertSame([0, 0, 0], $observed);
    }

    public function test_review_edit_emits_an_update_not_a_new_review_notification(): void
    {
        $observed = [];
        $review = Review::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        Event::listen('App\\Events\\ReviewUpdated', function () use (&$observed): void { $observed[] = DB::transactionLevel(); });
        Event::listen(ProposalReviewed::class, fn () => $this->fail('An edit is not a new review'));
        $this->actingAs($admin, 'sanctum')->putJson("/api/proposals/{$review->proposal_id}/reviews/{$review->id}", ['rating' => 5])->assertOk();
        $this->assertSame([0], $observed);
    }

    public function test_tag_only_proposal_edit_emits_an_update_and_noop_does_not(): void
    {
        $observed = [];
        $proposal = Proposal::factory()->create();
        Event::listen('App\\Events\\ProposalUpdated', function () use (&$observed): void { $observed[] = DB::transactionLevel(); });
        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", ['tags' => ['new-tag']])->assertOk();
        $this->putJson("/api/proposals/{$proposal->id}", ['tags' => ['new-tag']])->assertOk();
        $this->assertSame([0], $observed);
    }

    public function test_delete_rollback_preserves_attachment_and_emits_nothing(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/rollback.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 test');
        Proposal::deleting(fn () => throw new RuntimeException('Delete failed'));
        Event::listen('App\\Events\\ProposalDeleted', fn () => $this->fail('Rollback must not broadcast'));
        $this->actingAs($proposal->user, 'sanctum')->deleteJson("/api/proposals/{$proposal->id}")->assertStatus(500);
        $this->assertNotNull($proposal->fresh());
        Storage::disk('public')->assertExists($proposal->file_path);
    }
}
