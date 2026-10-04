<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProposalStatus;
use App\Jobs\IndexProposalJob;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use Tests\TestCase;

/**
 * Test IndexProposalJob.
 */
class IndexProposalJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job handles proposal indexing.
     */
    public function test_job_handles_proposal_indexing(): void
    {
        $proposal = Proposal::factory()->create();
        $proposal->loadMissing(['user', 'tags']);

        $job = new IndexProposalJob($proposal);
        $job->handle();
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id]);
    }

    /**
     * Scout queues indexing when a proposal is created.
     */
    public function test_job_is_dispatched_on_proposal_creation(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'speaker']);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/proposals', [
                'title' => 'Test Proposal',
                'description' => 'Test Description',
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(201);

        Queue::assertPushed(MakeSearchable::class, 1);
        $this->assertDatabaseHas('proposals', [
            'title' => 'Test Proposal',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Scout queues indexing when a proposal status changes.
     */
    public function test_job_is_dispatched_on_status_change(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        // Create proposal with explicit pending status
        $proposal = Proposal::factory()->create();
        $proposal->update(['status' => 'pending']);
        Queue::fake();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/proposals/{$proposal->id}/status", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200);
        Queue::assertPushed(MakeSearchable::class, 1);
        $proposal->refresh();
        $this->assertEquals(ProposalStatus::APPROVED, $proposal->status);
    }
}
