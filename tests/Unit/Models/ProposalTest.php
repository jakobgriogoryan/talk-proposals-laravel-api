<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ProposalStatus;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proposal model unit tests.
 */
class ProposalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test proposal belongs to user.
     */
    public function test_proposal_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $proposal = Proposal::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $proposal->user->id);
    }

    /**
     * Test proposal has tags.
     */
    public function test_proposal_has_tags(): void
    {
        $proposal = Proposal::factory()->create();
        $tag = Tag::factory()->create();
        $proposal->tags()->attach($tag->id);

        $this->assertTrue($proposal->tags->contains($tag));
    }

    /**
     * Test proposal has reviews.
     */
    public function test_proposal_has_reviews(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);

        $this->assertTrue($proposal->reviews->contains($review));
    }

    /**
     * Test proposal status scopes.
     */
    public function test_proposal_status_scopes(): void
    {
        Proposal::factory()->create(['status' => ProposalStatus::PENDING->value]);
        Proposal::factory()->create(['status' => ProposalStatus::APPROVED->value]);
        Proposal::factory()->create(['status' => ProposalStatus::REJECTED->value]);

        $this->assertEquals(1, Proposal::pending()->count());
        $this->assertEquals(1, Proposal::approved()->count());
        $this->assertEquals(1, Proposal::rejected()->count());
    }

    /**
     * Test proposal status check methods.
     */
    public function test_proposal_status_checks(): void
    {
        $pending = Proposal::factory()->create(['status' => ProposalStatus::PENDING->value]);
        $approved = Proposal::factory()->create(['status' => ProposalStatus::APPROVED->value]);
        $rejected = Proposal::factory()->create(['status' => ProposalStatus::REJECTED->value]);

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isApproved());
        $this->assertFalse($pending->isRejected());

        $this->assertTrue($approved->isApproved());
        $this->assertFalse($approved->isPending());
        $this->assertFalse($approved->isRejected());

        $this->assertTrue($rejected->isRejected());
        $this->assertFalse($rejected->isPending());
        $this->assertFalse($rejected->isApproved());
    }

    /**
     * Test proposal average rating calculation.
     */
    public function test_proposal_average_rating(): void
    {
        $proposal = Proposal::factory()->create();
        Review::factory()->create(['proposal_id' => $proposal->id, 'rating' => 4]);
        Review::factory()->create(['proposal_id' => $proposal->id, 'rating' => 5]);

        $proposal->load('reviews');

        $this->assertEquals(4.5, $proposal->getAverageRating());
    }

    /**
     * Test proposal search by title scope.
     */
    public function test_proposal_search_by_title_scope(): void
    {
        Proposal::factory()->create(['title' => 'Laravel Best Practices']);
        Proposal::factory()->create(['title' => 'Vue.js Advanced']);
        Proposal::factory()->create(['title' => 'Laravel Testing']);

        $results = Proposal::searchByTitle('Laravel')->get();

        $this->assertCount(2, $results);
    }
}
