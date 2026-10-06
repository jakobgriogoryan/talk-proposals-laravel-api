<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ReviewRating;
use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_speaker_can_list_and_view_reviews_for_own_proposal(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);

        $this->actingAs($speaker, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews")
            ->assertOk();

        $this->actingAs($speaker, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews/{$review->id}")
            ->assertOk();
    }

    public function test_speaker_cannot_access_reviews_for_another_speakers_proposal(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $otherSpeaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create(['user_id' => $otherSpeaker->id]);
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);

        $this->actingAs($speaker, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews")
            ->assertForbidden();

        $this->actingAs($speaker, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews/{$review->id}")
            ->assertForbidden();
    }

    public function test_reviewers_and_admins_can_access_reviews_for_any_proposal(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);

        foreach ([UserRole::REVIEWER, UserRole::ADMIN] as $role) {
            $user = User::factory()->create(['role' => $role->value]);

            $this->actingAs($user, 'sanctum')
                ->getJson("/api/proposals/{$proposal->id}/reviews/{$review->id}")
                ->assertOk();
        }
    }

    public function test_review_cannot_be_addressed_through_an_unrelated_proposal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $proposal = Proposal::factory()->create();
        $otherProposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $otherProposal->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews/{$review->id}")
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/proposals/{$proposal->id}/reviews/{$review->id}", [
                'rating' => ReviewRating::FIVE->value,
            ])
            ->assertNotFound();
    }

    public function test_review_endpoints_require_authentication(): void
    {
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create(['proposal_id' => $proposal->id]);

        $this->getJson("/api/proposals/{$proposal->id}/reviews")->assertUnauthorized();
        $this->getJson("/api/proposals/{$proposal->id}/reviews/{$review->id}")->assertUnauthorized();
    }
}
