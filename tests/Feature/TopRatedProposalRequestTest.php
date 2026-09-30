<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test TopRatedProposalRequest validation.
 */
class TopRatedProposalRequestTest extends TestCase
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
            ->getJson('/api/proposals/top-rated?limit=10');

        $this->assertNotEquals(422, $response->status());
    }

    /**
     * Test limit exceeds maximum is rejected.
     */
    public function test_limit_exceeds_maximum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals/top-rated?limit=100');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test limit below minimum is rejected.
     */
    public function test_limit_below_minimum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals/top-rated?limit=0');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test limit must be integer.
     */
    public function test_limit_must_be_integer(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/proposals/top-rated?limit=abc');

        $this->assertEquals(422, $response->status());
    }
}

