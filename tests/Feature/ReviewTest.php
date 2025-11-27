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

/**
 * Review feature tests.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test reviewer can create review.
     */
    public function test_reviewer_can_create_review(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create();

        $response = $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/proposals/{$proposal->id}/reviews", [
                'rating' => ReviewRating::FIVE->value,
                'comment' => 'Great proposal!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'review' => [
                        'id',
                        'rating',
                        'comment',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'proposal_id' => $proposal->id,
            'reviewer_id' => $reviewer->id,
            'rating' => ReviewRating::FIVE->value,
        ]);
    }

    /**
     * Test reviewer cannot create duplicate review.
     */
    public function test_reviewer_cannot_create_duplicate_review(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create();
        Review::factory()->create([
            'proposal_id' => $proposal->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->actingAs($reviewer, 'sanctum')
            ->postJson("/api/proposals/{$proposal->id}/reviews", [
                'rating' => ReviewRating::FOUR->value,
                'comment' => 'Another review',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test speaker cannot create review.
     */
    public function test_speaker_cannot_create_review(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $proposal = Proposal::factory()->create();

        $response = $this->actingAs($speaker, 'sanctum')
            ->postJson("/api/proposals/{$proposal->id}/reviews", [
                'rating' => ReviewRating::FIVE->value,
                'comment' => 'Great proposal!',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test admin can update review.
     */
    public function test_admin_can_update_review(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create();
        $review = Review::factory()->create([
            'proposal_id' => $proposal->id,
            'reviewer_id' => $reviewer->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/proposals/{$proposal->id}/reviews/{$review->id}", [
                'rating' => ReviewRating::TEN->value,
                'comment' => 'Updated comment',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => ReviewRating::TEN->value,
        ]);
    }

    /**
     * Test can list reviews for proposal.
     */
    public function test_can_list_reviews_for_proposal(): void
    {
        $user = User::factory()->create();
        $proposal = Proposal::factory()->create();
        Review::factory()->count(3)->create(['proposal_id' => $proposal->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'reviews',
                ],
            ]);
    }
}
