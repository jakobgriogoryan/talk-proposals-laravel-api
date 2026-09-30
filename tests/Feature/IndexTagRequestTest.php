<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test IndexTagRequest validation.
 */
class IndexTagRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Test valid request passes validation.
     */
    public function test_valid_request_passes(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tags?search=test&per_page=50');

        $this->assertNotEquals(422, $response->status());
    }

    /**
     * Test per_page exceeds maximum is rejected.
     */
    public function test_per_page_exceeds_maximum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tags?per_page=200');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test per_page below minimum is rejected.
     */
    public function test_per_page_below_minimum_rejected(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tags?per_page=0');

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test search query max length.
     */
    public function test_search_max_length(): void
    {
        $longSearch = str_repeat('a', 256);
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tags?search='.urlencode($longSearch));

        $this->assertEquals(422, $response->status());
    }
}

