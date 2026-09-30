<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proposal pagination feature tests.
 */
class ProposalPaginationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test proposals are paginated.
     */
    public function test_proposals_are_paginated(): void
    {
        $user = User::factory()->create();
        Proposal::factory()->count(25)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);
    }

    /**
     * Test can change per page.
     */
    public function test_can_change_per_page(): void
    {
        $user = User::factory()->create();
        Proposal::factory()->count(25)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response->json('data.pagination.per_page'));
    }

    /**
     * Test per page is limited to max.
     */
    public function test_per_page_is_limited_to_max(): void
    {
        $user = User::factory()->create();
        Proposal::factory()->count(25)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals?per_page=200'); // Exceeds max

        // Validation should reject values exceeding max
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    /**
     * Test can navigate pages.
     */
    public function test_can_navigate_pages(): void
    {
        $user = User::factory()->create();
        Proposal::factory()->count(25)->create();

        $response1 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals?page=1');

        $response2 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/proposals?page=2');

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        $this->assertEquals(1, $response1->json('data.pagination.current_page'));
        $this->assertEquals(2, $response2->json('data.pagination.current_page'));
    }
}
