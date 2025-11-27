<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Review;
use App\Models\User;
use App\Policies\ReviewPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review policy unit tests.
 */
class ReviewPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test reviewer can create reviews.
     */
    public function test_reviewer_can_create_reviews(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $policy = new ReviewPolicy;

        $this->assertTrue($policy->create($reviewer));
    }

    /**
     * Test speaker cannot create reviews.
     */
    public function test_speaker_cannot_create_reviews(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $policy = new ReviewPolicy;

        $this->assertFalse($policy->create($speaker));
    }

    /**
     * Test only admin can update reviews.
     */
    public function test_only_admin_can_update_reviews(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
        $policy = new ReviewPolicy;

        $this->assertTrue($policy->update($admin, $review));
        $this->assertFalse($policy->update($reviewer, $review));
    }
}
