<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test IndexAdminProposalRequest validation.
 */
class IndexAdminProposalRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test valid request passes validation.
     */
    public function test_valid_request_passes(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/proposals?search=test&status=pending&user_id=1&per_page=20');

        $this->assertNotEquals(422, $response->status());
    }

    /**
     * Test invalid user_id is rejected.
     */
    public function test_invalid_user_id_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/proposals?user_id=99999');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test invalid status is rejected.
     */
    public function test_invalid_status_rejected(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/proposals?status=invalid');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page validation.
     */
    public function test_per_page_validation(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/proposals?per_page=200');

        $this->assertEquals(422, $response->status());
    }
}

