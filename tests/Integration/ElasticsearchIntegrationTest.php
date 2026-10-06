<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\IndexProposalJob;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use App\Search\ElasticsearchProposalIndex;
use Elastic\Client\ClientBuilderInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Tests\TestCase;

// Explicit opt-in: php artisan test tests/Integration/ElasticsearchIntegrationTest.php
// Uses the test SQLite database and a unique, disposable local Elasticsearch index.
class ElasticsearchIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $createdIndex = null;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config([
            'scout.driver' => 'elastic', 'scout.prefix' => 'test_talk_'.bin2hex(random_bytes(8)).'_',
            'scout.queue' => true, 'queue.default' => 'database',
            'elastic.client.connections.default' => [
                'hosts' => ['http://127.0.0.1:9200'], 'retries' => 0,
                'httpClientOptions' => ['connect_timeout' => 2, 'timeout' => 5],
            ],
        ]);
        $index = app(ElasticsearchProposalIndex::class);
        $index->setup();
        $this->createdIndex = $index->name();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->createdIndex !== null) {
                app(ClientBuilderInterface::class)->default()->indices()->delete(['index' => $this->createdIndex]);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_full_text_filters_roles_and_pagination_against_real_elasticsearch(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $tags = Tag::factory()->count(2)->create();
        $matching = Proposal::factory()->count(2)->create([
            'user_id' => $speaker->id, 'title' => 'Indexed proposal',
            'description' => 'Unique elasticnectarine keyword in description', 'status' => 'approved',
        ]);
        foreach ($matching as $i => $proposal) {
            $proposal->tags()->attach($tags[$i]);
        }
        $other = Proposal::factory()->create(['title' => 'Other indexed proposal', 'description' => 'elasticnectarine', 'status' => 'approved']);
        $other->tags()->attach($tags[0]);
        $pending = Proposal::factory()->create(['user_id' => $speaker->id, 'description' => 'elasticnectarine', 'status' => 'pending']);
        $pending->tags()->attach($tags[0]);
        $this->index($matching->concat([$other, $pending])->all());
        $url = '/api/proposals?'.http_build_query([
            'search' => 'elasticnectarine', 'status' => 'approved', 'tags' => $tags->pluck('id')->all(), 'per_page' => 1,
        ]);
        $page1 = $this->actingAs($speaker, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.pagination.total', 2);
        $page2 = $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('data.pagination.current_page', 2);
        $this->assertNotSame($page1->json('data.proposals.0.id'), $page2->json('data.proposals.0.id'));
        $this->getJson($url.'&page=3')->assertOk()->assertJsonCount(0, 'data.proposals')->assertJsonPath('data.pagination.total', 2);
        $this->actingAs($reviewer, 'sanctum')->getJson('/api/review/proposals?search=elasticnectarine&status=approved')
            ->assertOk()->assertJsonPath('data.pagination.total', 3);
        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/proposals?search=elasticnectarine&status=approved&user_id={$speaker->id}")
            ->assertOk()->assertJsonPath('data.pagination.total', 2);
        $this->actingAs($speaker, 'sanctum')->getJson('/api/admin/proposals?search=elasticnectarine')->assertForbidden();
        $this->getJson('/api/proposals?search='.urlencode('user_id:* ('))->assertOk();
        $this->getJson('/api/proposals?search='.urlencode($speaker->email))->assertOk()->assertJsonCount(0, 'data.proposals');
    }

    public function test_native_scout_lifecycle_refreshes_content_tags_ratings_status_and_removes_deleted_proposals(): void
    {
        $proposal = Proposal::factory()->create(['title' => 'Initial title', 'description' => 'elastictamarind', 'status' => 'pending']);
        Queue::assertPushed(MakeSearchable::class);
        $this->index([$proposal]);
        $this->actingAs($proposal->user, 'sanctum')->getJson('/api/proposals?search=elastictamarind')
            ->assertOk()->assertJsonPath('data.pagination.total', 1);

        $proposal->update(['description' => 'elasticpineapple', 'status' => 'approved']);
        $tag = Tag::factory()->create(['name' => 'elasticdragonfruit']);
        $proposal->tags()->sync([$tag->id]);
        Review::factory()->create(['proposal_id' => $proposal->id, 'rating' => 5]);
        (new IndexProposalJob($proposal))->handle();
        $this->refreshIndex();
        $this->getJson('/api/proposals?search=elastictamarind')->assertOk()->assertJsonPath('data.pagination.total', 0);
        $this->getJson("/api/proposals?search=elasticdragonfruit&status=approved&tags={$tag->id}")
            ->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id);
        $client = app(ClientBuilderInterface::class)->default();
        $document = $client->get(['index' => $this->createdIndex, 'id' => (string) $proposal->id])->asArray()['_source'];
        $this->assertSame(5.0, (float) $document['average_rating']);
        $this->assertSame(1, $document['reviews_count']);

        $proposal->delete();
        Queue::assertPushed(RemoveFromSearch::class);
        (new RemoveFromSearch($proposal->newCollection([$proposal])))->handle();
        $this->refreshIndex();
        $this->assertSame(0, $client->count(['index' => $this->createdIndex])->asArray()['count']);
    }

    public function test_connection_failure_falls_back_without_leaking_other_owners_records(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $own = Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel fallback']);
        Proposal::factory()->create(['title' => 'Laravel fallback other']);
        config(['elastic.client.connections.default.hosts' => ['http://127.0.0.1:1']]);
        $this->app->forgetInstance(ClientBuilderInterface::class);
        try {
            $this->actingAs($speaker, 'sanctum')->getJson('/api/proposals?search=Laravel')->assertOk()
                ->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.proposals.0.id', $own->id);
        } finally {
            // Restore the real connection even on failure so cleanup remains scoped.
            config(['elastic.client.connections.default.hosts' => ['http://127.0.0.1:9200']]);
            $this->app->forgetInstance(ClientBuilderInterface::class);
        }
    }

    private function index(array $proposals): void
    {
        (new MakeSearchable((new Proposal)->newCollection(array_map(fn ($proposal) => $proposal->fresh(), $proposals))))->handle();
        $this->refreshIndex();
    }

    private function refreshIndex(): void
    {
        app(ClientBuilderInterface::class)->default()->indices()->refresh(['index' => $this->createdIndex]);
    }
}
