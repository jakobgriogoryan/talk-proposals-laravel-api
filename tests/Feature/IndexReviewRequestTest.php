<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test IndexReviewRequest validation.
 */
class IndexReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Proposal $proposal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'reviewer']);
        $this->proposal = Proposal::factory()->create();
    }

    /**
     * Test valid request passes validation.
     */
    public function test_valid_request_passes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/proposals/{$this->proposal->id}/reviews?per_page=20");

        $this->assertNotEquals(422, $response->status());
    }

    /**
     * Test per_page exceeds maximum is rejected.
     */
    public function test_per_page_exceeds_maximum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/proposals/{$this->proposal->id}/reviews?per_page=100");

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page below minimum is rejected.
     */
    public function test_per_page_below_minimum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/proposals/{$this->proposal->id}/reviews?per_page=0");

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page must be integer.
     */
    public function test_per_page_must_be_integer(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/proposals/{$this->proposal->id}/reviews?per_page=abc");

        $this->assertEquals(422, $response->status());
    }
}

