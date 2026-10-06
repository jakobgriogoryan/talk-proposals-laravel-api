<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IndexProposalJob;
use App\Listeners\IndexProposalOnReviewedListener;
use App\Listeners\ProcessProposalFileListener;
use App\Listeners\SendProposalSubmittedNotificationListener;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Jobs\MakeSearchable;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProposalSearchIndexingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue.default' => 'database',
            'scout.driver' => 'algolia',
            'scout.algolia.id' => 'TESTAPP123',
            'scout.algolia.secret' => 'test-admin-key',
        ]);
    }

    public function test_creation_commits_proposal_tags_and_file_when_algolia_is_unavailable(): void
    {
        Storage::fake('public');
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->andThrow(new RuntimeException('Algolia unavailable'));
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $speaker = User::factory()->create(['role' => 'speaker']);

        $response = $this->actingAs($speaker, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Proposal during search outage',
            'description' => 'Saving must not depend on the external search service.',
            'tags' => ['Laravel', 'PHP'],
            'file' => UploadedFile::fake()->createWithContent('proposal.pdf', '%PDF-1.4 test proposal'),
        ]);

        $response->assertCreated();
        $proposal = Proposal::findOrFail($response->json('data.proposal.id'));
        $this->assertEqualsCanonicalizing(['Laravel', 'PHP'], $proposal->tags->pluck('name')->all());
        Storage::disk('public')->assertExists($proposal->file_path);

        $jobs = DB::table('jobs')->get()->map(fn ($row) => json_decode($row->payload, true));
        $indexJobs = $jobs->where('displayName', MakeSearchable::class);
        $this->assertCount(1, $indexJobs);
        $this->assertStringNotContainsString('IndexProposalOnSubmittedListener', DB::table('jobs')->pluck('payload')->implode(''));
        $this->assertCount(1, $this->queuedJobs(ProcessProposalFileListener::class));
        $this->assertCount(1, $this->queuedJobs(SendProposalSubmittedNotificationListener::class));
        $job = unserialize($indexJobs->first()['data']['command']);

        try {
            $job->handle();
            $this->fail('An indexing failure must propagate to the queue worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Algolia unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('proposals', ['id' => $proposal->id, 'user_id' => $speaker->id]);
        Storage::disk('public')->assertExists($proposal->file_path);
    }

    public function test_deleted_proposals_do_not_leave_failed_custom_indexing_jobs(): void
    {
        Proposal::withoutSyncingToSearch(function (): void {
            $proposal = Proposal::factory()->create();
            IndexProposalJob::dispatch($proposal);
            $proposal->delete();
        });

        $this->assertSame(1, DB::table('jobs')->count());
        $this->artisan('queue:work', ['--stop-when-empty' => true, '--sleep' => 0])->assertSuccessful();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_indexing_is_queued_only_after_the_outer_transaction_commits(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        DB::beginTransaction();
        DB::beginTransaction();
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        $proposal->tags()->attach(Tag::factory()->create(['name' => 'Committed tag']));

        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
        DB::commit();
        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
        DB::commit();
        $this->assertCount(1, $this->queuedJobs(MakeSearchable::class));

        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->withArgs(function ($models) use ($proposal) {
            $this->assertSame($proposal->id, $models->first()->id);
            $this->assertSame(['Committed tag'], $models->first()->toSearchableArray()['tags']);

            return true;
        });
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $this->restoreJob($this->queuedJobs(MakeSearchable::class)->first())->handle();
    }

    public function test_rolled_back_proposals_are_not_indexed(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        DB::beginTransaction();
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id]);
        DB::rollBack();

        $this->assertDatabaseMissing('proposals', ['id' => $proposal->id]);
        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
    }

    public function test_native_indexing_retries_then_records_failure_without_removing_the_proposal(): void
    {
        config(['scout.queue' => ['connection' => 'database', 'queue' => 'search-test']]);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->times(3)->andThrow(new RuntimeException('Algolia unavailable'));
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $proposal = Proposal::factory()->create();

        foreach ([1, 2, 3] as $attempt) {
            $this->artisan('queue:work', [
                'connection' => 'database',
                '--queue' => 'search-test',
                '--once' => true,
                '--tries' => 3,
                '--backoff' => 0,
                '--sleep' => 0,
            ])->assertSuccessful();

            if ($attempt < 3) {
                $this->assertDatabaseHas('jobs', ['queue' => 'search-test', 'attempts' => $attempt]);
                $this->assertDatabaseCount('failed_jobs', 0);
            }
        }

        $this->assertDatabaseMissing('jobs', ['queue' => 'search-test']);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id]);
    }

    public function test_model_and_tag_updates_enqueue_one_index_job_with_current_data(): void
    {
        $proposal = $this->unindexedProposal();
        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'title' => 'Updated title',
            'tags' => ['New tag'],
        ])->assertOk();

        $this->assertCount(1, $this->queuedJobs(MakeSearchable::class));
        $this->assertCount(0, $this->queuedJobs(IndexProposalJob::class));
        $job = $this->restoreJob($this->queuedJobs(MakeSearchable::class)->first());
        $data = $job->models->first()->toSearchableArray();
        $this->assertSame('Updated title', $data['title']);
        $this->assertSame(['New tag'], $data['tags']);
    }

    public function test_tag_only_updates_are_still_indexed_on_the_worker(): void
    {
        $proposal = $this->unindexedProposal();
        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'tags' => ['Tag-only edit'],
        ])->assertOk();

        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
        $this->assertCount(1, $this->queuedJobs(IndexProposalJob::class));
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->withArgs(fn ($models) => $models->first()->toSearchableArray()['tags'] === ['Tag-only edit']
        );
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $this->restoreJob($this->queuedJobs(IndexProposalJob::class)->first())->handle();
        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
    }

    public function test_status_changes_do_not_duplicate_native_indexing(): void
    {
        $proposal = $this->unindexedProposal(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/proposals/{$proposal->id}/status", [
            'status' => 'approved',
        ])->assertOk();

        $this->assertCount(1, $this->queuedJobs(MakeSearchable::class));
        $this->assertCount(0, $this->queuedJobs(IndexProposalJob::class));
        $this->assertStringNotContainsString('IndexProposalOnStatusChangedListener', DB::table('jobs')->pluck('payload')->implode(''));
    }

    public function test_deletion_is_not_sent_to_the_index_until_commit_and_is_discarded_on_rollback(): void
    {
        $proposal = $this->unindexedProposal();
        DB::beginTransaction();
        $proposal->delete();
        $this->assertCount(0, $this->queuedJobs(RemoveFromSearch::class));
        DB::rollBack();
        $this->assertCount(0, $this->queuedJobs(RemoveFromSearch::class));
        $this->assertDatabaseHas('proposals', ['id' => $proposal->id]);

        DB::transaction(fn () => Proposal::findOrFail($proposal->id)->delete());
        $this->assertCount(1, $this->queuedJobs(RemoveFromSearch::class));
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('delete')->once()->withArgs(fn ($models) => $models->first()->id === $proposal->id);
        app(EngineManager::class)->extend('algolia', fn () => $engine);
        $this->restoreJob($this->queuedJobs(RemoveFromSearch::class)->first())->handle();
    }

    public function test_review_indexing_loads_current_ratings_without_dispatching_a_second_job(): void
    {
        $proposal = $this->unindexedProposal();
        Review::factory()->create(['proposal_id' => $proposal->id, 'rating' => 4]);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->withArgs(function ($models) {
            $data = $models->first()->toSearchableArray();
            $this->assertSame(4.0, $data['average_rating']);
            $this->assertSame(1, $data['reviews_count']);

            return true;
        });
        app(EngineManager::class)->extend('algolia', fn () => $engine);

        (new IndexProposalJob($proposal))->handle();
        $this->assertCount(0, $this->queuedJobs(MakeSearchable::class));
    }

    public function test_review_indexing_failures_propagate_for_retries(): void
    {
        $proposal = $this->unindexedProposal();
        $job = new IndexProposalJob($proposal);
        $this->assertTrue($job->afterCommit);
        $this->assertSame(3, $job->tries);
        $this->assertSame(5, $job->backoff);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('update')->once()->andThrow(new RuntimeException('Algolia unavailable'));
        app(EngineManager::class)->extend('algolia', fn () => $engine);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Algolia unavailable');
        $job->handle();
    }

    public function test_review_creation_preserves_the_queued_rating_indexing_listener(): void
    {
        $proposal = $this->unindexedProposal();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", [
            'rating' => 4,
        ])->assertCreated();

        $this->assertCount(1, $this->queuedJobs(IndexProposalOnReviewedListener::class));
        $listener = $this->restoreJob($this->queuedJobs(IndexProposalOnReviewedListener::class)->first());
        $listener->setJob(Mockery::mock(\Illuminate\Contracts\Queue\Job::class));
        $listener->handle($this->app);
        $this->assertCount(1, $this->queuedJobs(IndexProposalJob::class));
    }

    public function test_explicit_index_jobs_wait_for_commit_and_are_discarded_on_rollback(): void
    {
        $proposal = $this->unindexedProposal();
        DB::beginTransaction();
        IndexProposalJob::dispatch($proposal);
        $this->assertCount(0, $this->queuedJobs(IndexProposalJob::class));
        DB::rollBack();
        $this->assertCount(0, $this->queuedJobs(IndexProposalJob::class));

        DB::beginTransaction();
        IndexProposalJob::dispatch($proposal);
        $this->assertCount(0, $this->queuedJobs(IndexProposalJob::class));
        DB::commit();
        $this->assertCount(1, $this->queuedJobs(IndexProposalJob::class));
    }

    private function unindexedProposal(array $attributes = []): Proposal
    {
        return Proposal::withoutSyncingToSearch(fn () => Proposal::factory()->create($attributes));
    }

    private function queuedJobs(string $class): Collection
    {
        return DB::table('jobs')->get()
            ->map(fn ($row) => json_decode($row->payload, true))
            ->where('displayName', $class);
    }

    private function restoreJob(array $payload): object
    {
        return unserialize($payload['data']['command']);
    }
}
