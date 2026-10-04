<?php

declare(strict_types=1);

namespace Tests\Feature;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\UnreachableException;
use Algolia\AlgoliaSearch\Model\Search\GetApiKeyResponse;
use Algolia\AlgoliaSearch\Model\Search\SettingsResponse;
use App\Helpers\AlgoliaConfiguration;
use App\Models\Proposal;
use App\Models\Tag;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Contracts\UpdatesIndexSettings;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Algolia4Engine;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Jobs\MakeSearchable;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AlgoliaSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config([
            'scout.driver' => 'algolia', 'scout.algolia.id' => 'TESTAPP123',
            'scout.algolia.secret' => 'test-server-key', 'scout.prefix' => 'test_',
            'scout.queue' => true, 'queue.default' => 'database',
        ]);
    }

    public static function invalidCredentials(): array
    {
        return [
            'empty id' => ['', 'test-key'],
            'empty key' => ['TESTAPP123', ''],
            'example id' => ['your_app_id', 'test-key'],
            'example key' => ['TESTAPP123', 'your_admin_api_key'],
            'placeholder id' => ['placeholder', 'test-key'],
            'whitespace id' => [' TESTAPP123', 'test-key'],
            'whitespace key' => ['TESTAPP123', 'test key'],
        ];
    }

    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_use_local_search_and_fail_diagnostics_without_network(string $id, string $key): void
    {
        config(['scout.algolia.id' => $id, 'scout.algolia.secret' => $key]);
        $client = Mockery::mock(SearchClient::class);
        $client->shouldNotReceive('getApiKey');
        $this->useClient($client);
        $this->assertFalse(AlgoliaConfiguration::isConfigured());
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $this->assertSame('collection', config('scout.driver'));
        $this->artisan('scout:check-algolia')->expectsOutputToContain('credentials are missing, placeholders, or malformed')->assertFailed();
    }

    public function test_valid_configuration_is_not_overridden_and_native_settings_sync_targets_prefixed_index(): void
    {
        $this->app->getProvider(AppServiceProvider::class)->boot();
        $this->assertSame('algolia', config('scout.driver'));
        $engine = Mockery::mock(Engine::class, UpdatesIndexSettings::class);
        $engine->shouldReceive('updateIndexSettings')->once()->with('test_proposals', config('scout.algolia.index-settings.proposals'));
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $this->artisan('scout:sync-index-settings')->assertSuccessful();
    }

    public function test_invalid_runtime_configuration_does_not_queue_scout_index_or_removal_jobs(): void
    {
        config(['scout.algolia.id' => 'your_app_id']);
        $proposal = Proposal::factory()->create();
        $proposal->update(['title' => 'Updated without Algolia']);
        $proposal->delete();
        Queue::assertNothingPushed();
    }

    public function test_search_with_placeholders_uses_database_and_preserves_speaker_scope(): void
    {
        config(['scout.algolia.id' => 'your_app_id']);
        $proposal = Proposal::factory()->create(['title' => 'Laravel own']);
        Proposal::factory()->create(['title' => 'Laravel someone else']);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldNotReceive('paginate');
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $this->actingAs($proposal->user, 'sanctum')->getJson('/api/proposals?search=Laravel')
            ->assertOk()->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.proposals.0.id', $proposal->id);
    }

    public function test_diagnostics_verify_sdk_models_permissions_filters_and_query_without_writes(): void
    {
        $client = Mockery::mock(SearchClient::class);
        $client->shouldReceive('getApiKey')->once()->with('test-server-key')->andReturn(new GetApiKeyResponse([
            'acl' => ['search', 'addObject', 'deleteObject', 'settings', 'editSettings'],
        ]));
        $client->shouldReceive('getSettings')->once()->with('test_proposals')->andReturn(new SettingsResponse([
            'attributesForFaceting' => ['filterOnly(user_id)', 'status', 'searchable(tag_ids)'],
        ]));
        $client->shouldReceive('searchSingleIndex')->once()->with('test_proposals', [
            'query' => '', 'hitsPerPage' => 0, 'analytics' => false,
            'filters' => 'user_id:-1 AND status:pending AND tag_ids:-1',
        ])->andReturn([]);
        $this->useClient($client);
        $this->artisan('scout:check-algolia')->expectsOutputToContain('verified (read-only)')->assertSuccessful();
    }

    public function test_search_only_key_is_not_reported_as_ready_for_indexing(): void
    {
        $client = Mockery::mock(SearchClient::class);
        $client->shouldReceive('getApiKey')->once()->andReturn(['acl' => ['search']]);
        $client->shouldNotReceive('getSettings');
        $this->useClient($client);
        $this->artisan('scout:check-algolia')->expectsOutputToContain('missing required permissions')->assertFailed();
    }

    public function test_unconfigured_filters_fail_diagnostics(): void
    {
        $client = Mockery::mock(SearchClient::class);
        $client->shouldReceive('getApiKey')->andReturn(['acl' => ['search', 'addObject', 'deleteObject', 'settings', 'editSettings']]);
        $client->shouldReceive('getSettings')->andReturn(['attributesForFaceting' => ['user_id']]);
        $client->shouldNotReceive('searchSingleIndex');
        $this->useClient($client);
        $this->artisan('scout:check-algolia')->expectsOutputToContain('scout:sync-index-settings')->assertFailed();
    }

    public function test_diagnostic_errors_do_not_print_credentials_or_raw_requests(): void
    {
        $client = Mockery::mock(SearchClient::class);
        $client->shouldReceive('getApiKey')->andThrow(new UnreachableException('TESTAPP123 test-server-key secret request'));
        $this->useClient($client);
        $this->assertSame(1, Artisan::call('scout:check-algolia'));
        $this->assertStringContainsString('Check connectivity', Artisan::output());
        $this->assertStringNotContainsString('TESTAPP123', Artisan::output());
        $this->assertStringNotContainsString('test-server-key', Artisan::output());
    }

    public function test_import_reports_queue_acceptance_not_completed_indexing(): void
    {
        $proposal = Proposal::factory()->create();
        Queue::fake();
        $this->artisan('scout:import-proposals', ['--chunk' => 1])
            ->expectsOutputToContain('Queued 1/1 proposals for indexing')->assertSuccessful();
        Queue::assertPushed(MakeSearchable::class, fn ($job) => $job->models->first()->id === $proposal->id);
    }

    public function test_native_algolia_engine_indexes_and_searches_with_the_sdk_contract(): void
    {
        $proposal = Proposal::factory()->create(['title' => 'Laravel SDK contract', 'status' => 'approved']);
        $tag = Tag::factory()->create();
        $proposal->tags()->attach($tag);
        $client = Mockery::mock(SearchClient::class);
        $client->shouldReceive('saveObjects')->once()->withArgs(fn ($index, $objects) => $index === 'test_proposals'
            && $objects[0]['objectID'] === $proposal->id
            && $objects[0]['user_id'] === $proposal->user_id
            && $objects[0]['tag_ids'] === [$tag->id]
            && $objects[0]['status'] === 'approved')->andReturn([]);
        $client->shouldReceive('searchSingleIndex')->once()->withArgs(fn ($index, $params) => $index === 'test_proposals'
            && $params['query'] === 'Laravel'
            && str_contains($params['filters'], 'user_id:'.$proposal->user_id)
            && str_contains($params['filters'], 'status:approved')
            && str_contains($params['filters'], 'tag_ids:'.$tag->id))->andReturn([
                'hits' => [['objectID' => (string) $proposal->id]], 'nbHits' => 1,
            ]);
        $this->useClient($client);

        (new MakeSearchable($proposal->newCollection([$proposal->fresh()])))->handle();
        $this->actingAs($proposal->user, 'sanctum')->getJson("/api/proposals?search=Laravel&status=approved&tags={$tag->id}")
            ->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_import_failure_returns_nonzero_and_does_not_claim_success(): void
    {
        Proposal::factory()->create();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        $this->artisan('scout:import-proposals')
            ->expectsOutputToContain('Queued 0/1 proposals for indexing')->assertFailed();
    }

    public function test_import_rejects_invalid_chunk_and_unsafe_synchronous_queue(): void
    {
        $this->artisan('scout:import-proposals', ['--chunk' => 0])
            ->expectsOutputToContain('positive integer')->assertFailed();
        config(['queue.default' => 'sync']);
        $this->artisan('scout:import-proposals')->expectsOutputToContain('asynchronous queue')->assertFailed();
        $this->artisan('scout:check-algolia')->expectsOutputToContain('asynchronous queue')->assertFailed();
    }

    private function useClient(SearchClient $client): void
    {
        app(EngineManager::class)->extend('algolia', fn () => new Algolia4Engine($client));
    }
}
