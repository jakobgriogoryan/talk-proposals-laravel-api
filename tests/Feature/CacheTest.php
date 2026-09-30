<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\CacheHelper;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test caching functionality and invalidation.
 */
class CacheTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Cache::flush();
    }

    /**
     * Test tags are cached.
     */
    public function test_tags_are_cached(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create a tag
        Tag::factory()->create(['name' => 'Test Tag']);

        // First request - should hit database
        $response1 = $this->getJson('/api/tags');
        $this->assertEquals(200, $response1->status());

        // Delete tag from database (but cache should still have it)
        Tag::query()->delete();

        // Second request - should hit cache
        $response2 = $this->getJson('/api/tags');
        $this->assertEquals(200, $response2->status());
        $this->assertEquals($response1->json(), $response2->json());
    }

    /**
     * Test tags cache is invalidated on create.
     */
    public function test_tags_cache_invalidated_on_create(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create initial tag and cache it
        Tag::factory()->create(['name' => 'Tag 1']);
        $this->getJson('/api/tags');

        // Verify cache exists
        $cacheKey = CacheHelper::tagsKey();
        $this->assertTrue(Cache::has($cacheKey));

        // Create new tag
        $this->postJson('/api/tags', ['name' => 'Tag 2']);

        // Cache should be invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }

    /**
     * Test top-rated proposals are cached.
     */
    public function test_top_rated_proposals_are_cached(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // First request
        $response1 = $this->getJson('/api/proposals/top-rated');
        $this->assertEquals(200, $response1->status());

        // Verify cache exists
        $cacheKey = CacheHelper::topRatedKey(10);
        $this->assertTrue(Cache::has($cacheKey));

        // Second request should use cache
        $response2 = $this->getJson('/api/proposals/top-rated');
        $this->assertEquals(200, $response2->status());
        $this->assertEquals($response1->json(), $response2->json());
    }

    /**
     * Test cache invalidation on proposal update.
     */
    public function test_cache_invalidated_on_proposal_update(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create proposal and cache top-rated
        $proposal = $this->user->proposals()->create([
            'title' => 'Test Proposal',
            'description' => 'Test',
            'status' => 'approved',
        ]);

        $this->getJson('/api/proposals/top-rated');

        // Verify cache exists
        $cacheKey = CacheHelper::topRatedKey(10);
        $this->assertTrue(Cache::has($cacheKey));

        // Update proposal
        $this->putJson("/api/proposals/{$proposal->id}", [
            'title' => 'Updated Title',
        ]);

        // Cache should be invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }

    /**
     * Test cache invalidation on proposal delete.
     */
    public function test_cache_invalidated_on_proposal_delete(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create proposal and cache top-rated
        $proposal = $this->user->proposals()->create([
            'title' => 'Test Proposal',
            'description' => 'Test',
            'status' => 'approved',
        ]);

        $this->getJson('/api/proposals/top-rated');

        // Verify cache exists
        $cacheKey = CacheHelper::topRatedKey(10);
        $this->assertTrue(Cache::has($cacheKey));

        // Delete proposal
        $this->deleteJson("/api/proposals/{$proposal->id}");

        // Cache should be invalidated
        $this->assertFalse(Cache::has($cacheKey));
    }
}

