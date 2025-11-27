<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User model unit tests.
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user is admin check.
     */
    public function test_user_is_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isReviewer());
        $this->assertTrue($user->isSpeaker());
    }

    /**
     * Test user is reviewer check.
     */
    public function test_user_is_reviewer(): void
    {
        $user = User::factory()->create(['role' => UserRole::REVIEWER->value]);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->isReviewer());
        $this->assertFalse($user->isSpeaker());
    }

    /**
     * Test user is speaker check.
     */
    public function test_user_is_speaker(): void
    {
        $user = User::factory()->create(['role' => UserRole::SPEAKER->value]);

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isReviewer());
        $this->assertTrue($user->isSpeaker());
    }

    /**
     * Test user role enum getter.
     */
    public function test_get_role_enum(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN->value]);

        $this->assertInstanceOf(UserRole::class, $user->getRoleEnum());
        $this->assertEquals(UserRole::ADMIN, $user->getRoleEnum());
    }

    /**
     * Test user has proposals relationship.
     */
    public function test_user_has_proposals(): void
    {
        $user = User::factory()->create();
        $proposal = \App\Models\Proposal::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->proposals->contains($proposal));
    }

    /**
     * Test user has reviews relationship.
     */
    public function test_user_has_reviews(): void
    {
        $user = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $review = \App\Models\Review::factory()->create(['reviewer_id' => $user->id]);

        $this->assertTrue($user->reviews->contains($review));
    }
}
