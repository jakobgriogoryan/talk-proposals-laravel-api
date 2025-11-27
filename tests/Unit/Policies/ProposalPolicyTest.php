<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\User;
use App\Policies\ProposalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proposal policy unit tests.
 */
class ProposalPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can view any proposals.
     */
    public function test_admin_can_view_any_proposals(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->viewAny($admin));
    }

    /**
     * Test speaker can view their own proposal.
     */
    public function test_speaker_can_view_own_proposal(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->view($speaker, $proposal));
    }

    /**
     * Test speaker cannot view other speaker's proposal.
     */
    public function test_speaker_cannot_view_other_speaker_proposal(): void
    {
        $speaker1 = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $speaker2 = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker2->id]);
        $policy = new ProposalPolicy;

        $this->assertFalse($policy->view($speaker1, $proposal));
    }

    /**
     * Test reviewer can view any proposal.
     */
    public function test_reviewer_can_view_any_proposal(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create();
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->view($reviewer, $proposal));
    }

    /**
     * Test speaker can create proposals.
     */
    public function test_speaker_can_create_proposals(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->create($speaker));
    }

    /**
     * Test reviewer cannot create proposals.
     */
    public function test_reviewer_cannot_create_proposals(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $policy = new ProposalPolicy;

        $this->assertFalse($policy->create($reviewer));
    }

    /**
     * Test user can update own proposal.
     */
    public function test_user_can_update_own_proposal(): void
    {
        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $user->id]);
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->update($user, $proposal));
    }

    /**
     * Test admin can update any proposal.
     */
    public function test_admin_can_update_any_proposal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $proposal = Proposal::factory()->create();
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->update($admin, $proposal));
    }

    /**
     * Test only admin can update proposal status.
     */
    public function test_only_admin_can_update_proposal_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        $policy = new ProposalPolicy;

        $this->assertTrue($policy->updateStatus($admin, $proposal));
        $this->assertFalse($policy->updateStatus($speaker, $proposal));
    }
}
