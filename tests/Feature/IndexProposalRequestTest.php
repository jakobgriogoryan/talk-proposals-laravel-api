<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test IndexProposalRequest validation.
 */
class IndexProposalRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'speaker']);
    }

    /**
     * Test valid request passes validation.
     */
    public function test_valid_request_passes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?search=test&status=pending&per_page=20');

        $this->assertNotEquals(422, $response->status());
    }

    /**
     * Test invalid status is rejected.
     */
    public function test_invalid_status_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?status=invalid');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page exceeds maximum is rejected.
     */
    public function test_per_page_exceeds_maximum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?per_page=200');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page below minimum is rejected.
     */
    public function test_per_page_below_minimum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?per_page=0');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test tags can be array or comma-separated string.
     */
    public function test_tags_accepts_array_or_string(): void
    {
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?tags[]=1&tags[]=2');

        $response2 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals?tags=1,2');

        $this->assertNotEquals(422, $response1->status());
        $this->assertNotEquals(422, $response2->status());
    }
}

