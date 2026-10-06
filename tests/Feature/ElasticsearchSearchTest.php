<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\Tag;
use App\Models\User;
use App\Search\ElasticsearchProposalIndex;
use App\Search\ProposalSearchParametersFactory;
use Elastic\Client\ClientBuilderInterface;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\TestCase;

class ElasticsearchSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['scout.driver' => 'elastic', 'scout.prefix' => 'test_', 'queue.default' => 'database']);
    }

    public function test_native_driver_applies_plain_text_search_and_role_status_or_tag_filters(): void
    {
        $proposal = Proposal::factory()->create(['status' => 'approved']);
        $tags = Tag::factory()->count(2)->create();
        $proposal->tags()->attach($tags->first());
        $query = 'Laravel (user_id:*)';
        $this->useHttp(function (RequestInterface $request) use ($proposal, $tags, $query): Response {
            $body = json_decode((string) $request->getBody(), true);
            $this->assertSame('/test_proposals/_search', $request->getUri()->getPath());
            $this->assertSame($query, $body['query']['bool']['must']['multi_match']['query']);
            $this->assertSame(['title^3', 'description', 'tags', 'user_name'], $body['query']['bool']['must']['multi_match']['fields']);
            $this->assertSame([
                ['term' => ['user_id' => $proposal->user_id]], ['term' => ['status' => 'approved']],
                ['bool' => ['must' => [['terms' => ['tag_ids' => $tags->pluck('id')->all()]]]]],
            ], $body['query']['bool']['filter']);
            $this->assertTrue($body['track_total_hits']);
            $this->assertFalse($body['_source']);
            $this->assertSame(1, $body['size']);

            return $this->hits([$proposal->id]);
        });
        $url = '/api/proposals?'.http_build_query(['search' => $query, 'status' => 'approved', 'tags' => $tags->pluck('id')->all(), 'per_page' => 1]);
        $this->actingAs($proposal->user, 'sanctum')->getJson($url)->assertOk()
            ->assertJsonPath('data.proposals.0.id', $proposal->id)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_stale_index_cannot_return_another_speakers_proposal(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $own = Proposal::factory()->create(['user_id' => $speaker->id]);
        $other = Proposal::factory()->create();
        $this->useHttp(fn () => $this->hits([$other->id, $own->id]));
        $this->actingAs($speaker, 'sanctum')->getJson('/api/proposals?search=Laravel')->assertOk()
            ->assertJsonCount(1, 'data.proposals')->assertJsonPath('data.proposals.0.id', $own->id);
    }

    public function test_stale_status_and_tag_hits_are_removed_during_database_hydration(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $tag = Tag::factory()->create();
        $matching = Proposal::factory()->create(['status' => 'approved']);
        $matching->tags()->attach($tag);
        $wrongStatus = Proposal::factory()->create(['status' => 'pending']);
        $wrongStatus->tags()->attach($tag);
        $wrongTag = Proposal::factory()->create(['status' => 'approved']);
        $this->useHttp(fn () => $this->hits([$wrongStatus->id, $wrongTag->id, $matching->id]));
        $this->actingAs($reviewer, 'sanctum')->getJson("/api/review/proposals?search=Laravel&status=approved&tags={$tag->id}")
            ->assertOk()->assertJsonCount(1, 'data.proposals')->assertJsonPath('data.proposals.0.id', $matching->id);
    }

    public function test_admin_filter_uses_exact_user_id(): void
    {
        $proposal = Proposal::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->useHttp(function (RequestInterface $request) use ($proposal): Response {
            $body = json_decode((string) $request->getBody(), true);
            $this->assertSame([['term' => ['user_id' => $proposal->user_id]]], $body['query']['bool']['filter']);

            return $this->hits([$proposal->id]);
        });
        $this->actingAs($admin, 'sanctum')->getJson("/api/admin/proposals?search=Laravel&user_id={$proposal->user_id}")
            ->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id);
    }

    public function test_reviewer_search_has_no_owner_restriction(): void
    {
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $proposal = Proposal::factory()->create();
        $this->useHttp(function (RequestInterface $request) use ($proposal): Response {
            $body = json_decode((string) $request->getBody(), true);
            $this->assertArrayNotHasKey('filter', $body['query']['bool']);

            return $this->hits([$proposal->id]);
        });
        $this->actingAs($reviewer, 'sanctum')->getJson('/api/review/proposals?search=Laravel')
            ->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id);
    }

    public function test_empty_page_keeps_total_and_requested_page(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $this->useHttp(function (RequestInterface $request): Response {
            $body = json_decode((string) $request->getBody(), true);
            $this->assertSame(20, $body['from']);

            return $this->hits([], 1);
        });
        $this->actingAs($speaker, 'sanctum')->getJson('/api/proposals?search=Laravel&page=3&per_page=10')
            ->assertOk()->assertJsonPath('data.pagination.current_page', 3)->assertJsonPath('data.pagination.total', 1);
    }

    public static function outages(): array
    {
        return ['index missing' => [404], 'credentials rejected' => [403], 'unavailable' => [503]];
    }

    #[DataProvider('outages')]
    public function test_remote_errors_fall_back_to_database_with_the_same_filters(int $status): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $tag = Tag::factory()->create();
        $own = Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel match', 'status' => 'approved']);
        $own->tags()->attach($tag);
        Proposal::factory()->create(['title' => 'Laravel other', 'status' => 'approved'])->tags()->attach($tag);
        Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel pending', 'status' => 'pending'])->tags()->attach($tag);
        $this->useHttp(fn () => new Response($status, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], '{"error":"test failure"}'));
        $this->actingAs($speaker, 'sanctum')->getJson("/api/proposals?search=Laravel&status=approved&tags={$tag->id}")
            ->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.proposals.0.id', $own->id);
    }

    public function test_unrelated_programming_failure_is_not_masked_as_a_search_outage(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $this->useHttp(fn () => throw new RuntimeException('Not an external service failure'));
        $this->actingAs($speaker, 'sanctum')->getJson('/api/proposals?search=Laravel')->assertStatus(500);
    }

    public function test_partial_search_response_falls_back_instead_of_returning_incomplete_results(): void
    {
        $proposal = Proposal::factory()->create(['title' => 'Laravel fallback']);
        $this->useHttp(fn () => $this->elasticResponse(['timed_out' => true, '_shards' => ['failed' => 1],
            'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]]));
        $this->actingAs($proposal->user, 'sanctum')->getJson('/api/proposals?search=Laravel')->assertOk()
            ->assertJsonPath('data.proposals.0.id', $proposal->id)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_no_query_and_deep_pagination_use_database_without_elasticsearch_requests(): void
    {
        $proposal = Proposal::factory()->create(['title' => 'Laravel local']);
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldNotReceive('sendRequest');
        $client = ClientBuilder::create()->setHosts(['http://localhost:9200'])->setHttpClient($http)->build();
        $builder = Mockery::mock(ClientBuilderInterface::class);
        $builder->shouldReceive('default')->andReturn($client);
        $this->app->instance(ClientBuilderInterface::class, $builder);
        $this->actingAs($proposal->user, 'sanctum')->getJson('/api/proposals')->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id);
        $this->getJson('/api/proposals?search=Laravel&page=1001&per_page=10')->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1001)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_diagnostic_failures_do_not_print_raw_server_errors_or_credentials(): void
    {
        $this->useHttp(fn () => throw new RuntimeException('https://secret-key@private-server.example'));
        $this->artisan('scout:check-elastic')->expectsOutputToContain('Elasticsearch check failed')
            ->doesntExpectOutputToContain('secret-key')->doesntExpectOutputToContain('private-server.example')->assertFailed();
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('Elasticsearch setup failed')
            ->doesntExpectOutputToContain('secret-key')->assertFailed();
    }

    public function test_native_bulk_index_uses_the_proposal_schema(): void
    {
        $proposal = Proposal::factory()->create();
        $tag = Tag::factory()->create();
        $proposal->tags()->attach($tag);
        $this->useHttp(function (RequestInterface $request) use ($proposal, $tag): Response {
            $this->assertSame('/test_proposals/_bulk', $request->getUri()->getPath());
            $lines = explode("\n", trim((string) $request->getBody()));
            $this->assertSame(['index' => ['_id' => (string) $proposal->id]], json_decode($lines[0], true));
            $document = json_decode($lines[1], true);
            $this->assertSame($proposal->user_id, $document['user_id']);
            $this->assertSame([$tag->id], $document['tag_ids']);

            return $this->elasticResponse(['errors' => false]);
        });
        (new MakeSearchable($proposal->newCollection([$proposal->fresh()])))->handle();
    }

    public function test_setup_creates_explicit_mapping_without_overwriting_existing_indices(): void
    {
        $this->useHttp(function (RequestInterface $request): Response {
            return match ($request->getMethod().' '.$request->getUri()->getPath()) {
                'GET /' => $this->elasticResponse(['version' => ['number' => '8.19.22']]),
                'HEAD /test_proposals' => new Response(404, ['X-Elastic-Product' => 'Elasticsearch']),
                'PUT /test_proposals' => $this->createdIndex($request),
                default => throw new RuntimeException('Unexpected Elasticsearch call'),
            };
        });
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('No existing index was replaced')->assertSuccessful();
    }

    public function test_setup_refuses_incompatible_existing_mappings_without_deleting_data(): void
    {
        $this->useHttp(fn (RequestInterface $request) => match ($request->getMethod().' '.$request->getUri()->getPath()) {
            'GET /' => $this->elasticResponse(['version' => ['number' => '8.19.22']]),
            'HEAD /test_proposals' => new Response(200, ['X-Elastic-Product' => 'Elasticsearch']),
            'GET /test_proposals/_mapping' => $this->elasticResponse(['test_proposals' => ['mappings' => ['properties' => ['status' => ['type' => 'text']]]]]),
            default => throw new RuntimeException('Must not modify an existing index'),
        });
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('Existing data was not changed')->assertFailed();
    }

    public function test_diagnostics_are_read_only_and_do_not_claim_write_permissions(): void
    {
        $this->useHttp(fn (RequestInterface $request) => match ($request->getMethod().' '.$request->getUri()->getPath()) {
            'GET /' => $this->elasticResponse(['version' => ['number' => '8.19.22']]),
            'GET /test_proposals/_mapping' => $this->elasticResponse(['test_proposals' => ['mappings' => ['properties' => ElasticsearchProposalIndex::PROPERTIES]]]),
            'POST /test_proposals/_search' => $this->hits([]),
            default => throw new RuntimeException('Diagnostics must not write'),
        });
        $this->artisan('scout:check-elastic')->expectsOutputToContain('verified (read-only)')
            ->expectsOutputToContain('does not verify write permissions')->assertSuccessful();
    }

    public function test_blank_query_uses_match_all_and_preserves_native_filters(): void
    {
        $parameters = app(ProposalSearchParametersFactory::class)->makeFromBuilder(
            Proposal::search('   ')->where('status', 'pending')
        )->toArray();
        $this->assertInstanceOf(\stdClass::class, $parameters['body']['query']['bool']['must']['match_all']);
        $this->assertSame([['term' => ['status' => 'pending']]], $parameters['body']['query']['bool']['filter']);
        $this->assertTrue($parameters['body']['track_total_hits']);
        $this->assertFalse($parameters['body']['_source']);
    }

    public function test_setup_rejects_an_index_that_makes_email_searchable(): void
    {
        $properties = ElasticsearchProposalIndex::PROPERTIES;
        $properties['user_email']['index'] = true;
        $this->useHttp(fn (RequestInterface $request) => match ($request->getMethod().' '.$request->getUri()->getPath()) {
            'GET /' => $this->elasticResponse(['version' => ['number' => '8.19.22']]),
            'HEAD /test_proposals' => new Response(200, ['X-Elastic-Product' => 'Elasticsearch']),
            'GET /test_proposals/_mapping' => $this->elasticResponse(['test_proposals' => ['mappings' => ['properties' => $properties]]]),
            default => throw new RuntimeException('Must not change an existing index'),
        });
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('Existing data was not changed')->assertFailed();
    }

    public function test_import_does_not_skip_next_chunk_when_an_earlier_row_is_deleted(): void
    {
        $proposals = Proposal::factory()->count(4)->create();
        $firstId = $proposals[0]->id;
        $secondId = $proposals[1]->id;
        Proposal::retrieved(function (Proposal $proposal) use ($firstId, $secondId): void {
            if ($proposal->id === $secondId) {
                Proposal::whereKey($firstId)->delete();
            }
        });
        Queue::fake();
        $this->artisan('scout:import-proposals', ['--chunk' => 2])->assertSuccessful();
        Queue::assertPushed(MakeSearchable::class, 4);
        foreach ($proposals->slice(2) as $proposal) {
            Queue::assertPushed(MakeSearchable::class, fn ($job) => $job->models->first()->id === $proposal->id);
        }
    }

    public function test_wrong_server_major_and_invalid_prefix_fail_without_claiming_readiness(): void
    {
        $this->useHttp(fn () => $this->elasticResponse(['version' => ['number' => '9.0.0']]));
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('requires Elasticsearch 8.x')->assertFailed();
        config(['scout.prefix' => 'INVALID_']);
        $this->artisan('scout:setup-elastic')->expectsOutputToContain('valid lowercase')->assertFailed();
    }

    public function test_import_accepts_elastic_and_queues_proposals(): void
    {
        $proposal = Proposal::factory()->create();
        Queue::fake();
        $this->artisan('scout:import-proposals')->expectsOutputToContain('Queued 1/1 proposals')->assertSuccessful();
        Queue::assertPushed(MakeSearchable::class, fn ($job) => $job->models->first()->id === $proposal->id);
    }

    private function useHttp(callable $callback): void
    {
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('sendRequest')->andReturnUsing($callback);
        $client = ClientBuilder::create()->setHosts(['http://localhost:9200'])->setHttpClient($http)->setRetries(0)->build();
        $builder = Mockery::mock(ClientBuilderInterface::class);
        $builder->shouldReceive('default')->andReturn($client);
        $this->app->instance(ClientBuilderInterface::class, $builder);
    }

    private function hits(array $ids, ?int $total = null): Response
    {
        return $this->elasticResponse(['hits' => ['total' => ['value' => $total ?? count($ids), 'relation' => 'eq'],
            'hits' => array_map(fn ($id) => ['_id' => (string) $id, '_index' => 'test_proposals'], $ids)]]);
    }

    private function elasticResponse(array $data): Response
    {
        return new Response(200, ['X-Elastic-Product' => 'Elasticsearch', 'Content-Type' => 'application/json'], json_encode($data));
    }

    private function createdIndex(RequestInterface $request): Response
    {
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(ElasticsearchProposalIndex::PROPERTIES, $body['mappings']['properties']);
        $this->assertSame('strict', $body['mappings']['dynamic']);

        return $this->elasticResponse(['acknowledged' => true]);
    }
}
