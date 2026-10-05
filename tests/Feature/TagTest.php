<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tag feature tests.
 */
class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_cached_tag_pages_and_page_sizes_remain_independent(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 25) as $number) {
            Tag::factory()->create(['name' => sprintf('Tag %02d', $number)]);
        }
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/tags?page=1&per_page=10')->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Tag 01');
        $this->getJson('/api/tags?page=2&per_page=10')->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.tags.0.name', 'Tag 11');
        $this->getJson('/api/tags?page=1&per_page=20')->assertOk()
            ->assertJsonCount(20, 'data.tags')
            ->assertJsonPath('data.pagination.per_page', 20);
    }

    public function test_tag_creation_invalidates_every_cached_search_and_page(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        Tag::factory()->create(['name' => 'Laravel']);
        $this->getJson('/api/tags?search=Vue&per_page=1')->assertJsonCount(0, 'data.tags');
        $this->getJson('/api/tags?page=2&per_page=1')->assertJsonCount(0, 'data.tags');

        $this->postJson('/api/tags', ['name' => 'Vue'])->assertCreated();

        $this->getJson('/api/tags?search=Vue&per_page=1')->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Vue');
        $this->getJson('/api/tags?page=2&per_page=1')->assertOk()
            ->assertJsonPath('data.tags.0.name', 'Vue');
    }

    /**
     * Test can list tags.
     */
    public function test_can_list_tags(): void
    {
        $user = User::factory()->create();
        Tag::factory()->count(5)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'tags',
                ],
            ]);
    }

    /**
     * Test can search tags.
     */
    public function test_can_search_tags(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['name' => 'Laravel']);
        Tag::factory()->create(['name' => 'Vue.js']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tags?search=Laravel');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.tags');
    }

    /**
     * Test can create tag.
     */
    public function test_can_create_tag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tags', [
                'name' => 'New Tag',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'tag' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'New Tag',
        ]);
    }

    /**
     * Test creating duplicate tag returns existing.
     */
    public function test_creating_duplicate_tag_returns_existing(): void
    {
        $user = User::factory()->create();
        $existingTag = Tag::factory()->create(['name' => 'Existing Tag']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tags', [
                'name' => 'Existing Tag',
            ]);

        $response->assertStatus(201);

        $this->assertEquals($existingTag->id, $response->json('data.tag.id'));
    }

    /**
     * Test tag validation.
     */
    public function test_tag_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tags', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
