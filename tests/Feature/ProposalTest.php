<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proposal feature tests.
 */
class ProposalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test speaker can create proposal.
     */
    public function test_speaker_can_create_proposal(): void
    {
        Storage::fake('public');

        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $file = UploadedFile::fake()->create('proposal.pdf', 100);

        $response = $this->actingAs($speaker, 'sanctum')
            ->postJson('/api/proposals', [
                'title' => 'Test Proposal',
                'description' => 'Test Description',
                'file' => $file,
                'tags' => ['Laravel', 'PHP'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'proposal' => [
                        'id',
                        'title',
                        'description',
                        'status',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('proposals', [
            'title' => 'Test Proposal',
            'user_id' => $speaker->id,
            'status' => ProposalStatus::PENDING->value,
        ]);
    }

    /**
     * Test reviewer cannot create proposal.
     */
    public function test_reviewer_cannot_create_proposal(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);

        $response = $this->actingAs($reviewer, 'sanctum')
            ->postJson('/api/proposals', [
                'title' => 'Test Proposal',
                'description' => 'Test Description',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test speaker can view own proposals.
     */
    public function test_speaker_can_view_own_proposals(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);

        $response = $this->actingAs($speaker, 'sanctum')
            ->getJson('/api/proposals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'proposals',
                    'pagination',
                ],
            ]);
    }

    /**
     * Test speaker can update own proposal.
     */
    public function test_speaker_can_update_own_proposal(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);

        $response = $this->actingAs($speaker, 'sanctum')
            ->putJson("/api/proposals/{$proposal->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test speaker can delete own proposal.
     */
    public function test_speaker_can_delete_own_proposal(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);

        $response = $this->actingAs($speaker, 'sanctum')
            ->deleteJson("/api/proposals/{$proposal->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('proposals', [
            'id' => $proposal->id,
        ]);
    }

    /**
     * Test admin can update proposal status.
     */
    public function test_admin_can_update_proposal_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $proposal = Proposal::factory()->create(['status' => ProposalStatus::PENDING->value]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/proposals/{$proposal->id}/status", [
                'status' => ProposalStatus::APPROVED->value,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('proposals', [
            'id' => $proposal->id,
            'status' => ProposalStatus::APPROVED->value,
        ]);
    }

    /**
     * Test top-rated proposals endpoint.
     */
    public function test_top_rated_proposals_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals/top-rated');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'proposals',
                ],
            ]);
    }
}
