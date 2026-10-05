<?php

declare(strict_types=1);

namespace Tests\Feature;

use Algolia\AlgoliaSearch\Exceptions\UnreachableException;
use App\Events\ProposalSubmitted;
use App\Helpers\CacheHelper;
use App\Jobs\IndexProposalJob;
use App\Jobs\ProcessProposalFileJob;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Tag;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    public function test_creation_event_failure_preserves_committed_attachment_and_returns_created(): void
    {
        Event::listen(ProposalSubmitted::class, fn () => throw new RuntimeException('Post-commit event failure'));
        $user = User::factory()->create(['role' => 'speaker']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Committed proposal',
            'description' => 'Creation must remain successful after the database commit.',
            'file' => UploadedFile::fake()->createWithContent('attachment.pdf', '%PDF-1.4 attachment'),
        ])->assertCreated();

        $proposal = Proposal::findOrFail($response->json('data.proposal.id'));
        $this->assertSame(1, Proposal::count());
        Storage::disk('public')->assertExists($proposal->file_path);
    }

    public function test_creation_after_commit_callback_failure_preserves_attachment(): void
    {
        Proposal::creating(function (): void {
            DB::afterCommit(fn () => throw new RuntimeException('Post-commit callback failure'));
        });
        $user = User::factory()->create(['role' => 'speaker']);

        $this->actingAs($user, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Committed proposal',
            'description' => 'A callback failure must not remove the durable upload.',
            'file' => UploadedFile::fake()->createWithContent('attachment.pdf', '%PDF-1.4 attachment'),
        ])->assertCreated();

        Storage::disk('public')->assertExists(Proposal::sole()->file_path);
    }

    public function test_failed_creation_rolls_back_record_and_cleans_upload(): void
    {
        Proposal::creating(fn () => throw new RuntimeException('Database write failed'));
        $user = User::factory()->create(['role' => 'speaker']);

        $this->actingAs($user, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Failed proposal',
            'description' => 'Pre-commit failure must remove its temporary upload.',
            'file' => UploadedFile::fake()->createWithContent('attachment.pdf', '%PDF-1.4 attachment'),
        ])->assertStatus(500);

        $this->assertSame(0, Proposal::count());
        $this->assertSame([], Storage::disk('public')->allFiles('proposals'));
    }

    public function test_invalid_argument_after_commit_does_not_report_creation_as_validation_failure(): void
    {
        Event::listen(ProposalSubmitted::class, fn () => throw new \InvalidArgumentException('Listener failed'));
        $user = User::factory()->create(['role' => 'speaker']);

        $this->actingAs($user, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Committed proposal',
            'description' => 'Post-commit exceptions are not request validation failures.',
            'file' => UploadedFile::fake()->createWithContent('attachment.pdf', '%PDF-1.4 attachment'),
        ])->assertCreated();

        Storage::disk('public')->assertExists(Proposal::sole()->file_path);
    }

    public function test_failed_file_replacement_preserves_original_and_removes_temporary_upload(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/original.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 original');
        Proposal::updating(fn () => throw new RuntimeException('Simulated database failure'));

        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 replacement'),
        ])->assertStatus(500);

        $this->assertSame('proposals/original.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists('proposals/original.pdf');
        $this->assertSame(['proposals/original.pdf'], Storage::disk('public')->allFiles('proposals'));
    }

    public function test_stale_file_job_cannot_restore_an_old_attachment(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/latest.pdf']);
        Storage::disk('public')->put('proposals/latest.pdf', '%PDF-1.4 latest');
        Storage::disk('public')->put('proposals/old.pdf', '%PDF-1.4 old');

        (new ProcessProposalFileJob($proposal, 'proposals/old.pdf', $proposal->user_id))
            ->handle(app(FileUploadService::class));

        $this->assertSame('proposals/latest.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists('proposals/latest.pdf');
    }

    public function test_successful_replacement_removes_original_only_after_commit(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/original.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 original');
        Proposal::updating(function (): void {
            Storage::disk('public')->assertExists('proposals/original.pdf');
        });

        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 replacement'),
        ])->assertOk();

        $this->assertNotSame('proposals/original.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists($proposal->fresh()->file_path);
        Storage::disk('public')->assertMissing('proposals/original.pdf');
    }

    public function test_invalid_replacement_preserves_original(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/original.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 original');

        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'file' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf'),
        ])->assertStatus(422);

        $this->assertSame('proposals/original.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists('proposals/original.pdf');
        $this->assertSame(['proposals/original.pdf'], Storage::disk('public')->allFiles('proposals'));
    }

    public function test_after_commit_failure_does_not_delete_committed_attachment(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/original.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 original');
        Proposal::updating(function (): void {
            DB::afterCommit(fn () => throw new RuntimeException('Simulated post-commit failure'));
        });

        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 replacement'),
        ])->assertStatus(500);

        $this->assertNotSame('proposals/original.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists($proposal->fresh()->file_path);
    }

    public function test_file_job_does_not_count_the_stored_attachment_twice_for_quota(): void
    {
        config(['app.file_storage.quota_per_user_mb' => 1]);
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/current.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 '.str_repeat('x', 600 * 1024));

        (new ProcessProposalFileJob($proposal, $proposal->file_path, $proposal->user_id))
            ->handle(app(FileUploadService::class));

        $this->assertSame('proposals/current.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists('proposals/current.pdf');
    }

    public function test_admin_replacement_uses_proposal_owner_quota(): void
    {
        config(['app.file_storage.quota_per_user_mb' => 1]);
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/original.pdf']);
        $other = Proposal::factory()->create(['user_id' => $proposal->user_id, 'file_path' => 'proposals/other.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 original');
        Storage::disk('public')->put($other->file_path, '%PDF-1.4 '.str_repeat('x', 800 * 1024));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'file' => UploadedFile::fake()->createWithContent('replacement.pdf', '%PDF-1.4 '.str_repeat('x', 600 * 1024)),
        ])->assertUnprocessable();

        $this->assertSame('proposals/original.pdf', $proposal->fresh()->file_path);
        Storage::disk('public')->assertExists('proposals/original.pdf');
    }

    public function test_file_job_transient_failure_keeps_attachment_and_stale_rejection_cannot_clear_new_file(): void
    {
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/current.pdf']);
        Storage::disk('public')->put($proposal->file_path, '%PDF-1.4 current');
        $job = new ProcessProposalFileJob($proposal, $proposal->file_path, $proposal->user_id);
        $job->failed(new RuntimeException('Temporary storage outage'));
        Storage::disk('public')->assertExists('proposals/current.pdf');
        $this->assertSame('proposals/current.pdf', $proposal->fresh()->file_path);

        $proposal->update(['file_path' => 'proposals/latest.pdf']);
        Storage::disk('public')->put('proposals/latest.pdf', '%PDF-1.4 latest');
        $job->failed(new \InvalidArgumentException('Rejected old attachment'));
        Storage::disk('public')->assertExists('proposals/latest.pdf');
        $this->assertSame('proposals/latest.pdf', $proposal->fresh()->file_path);
    }

    public function test_multipart_empty_tags_clear_relations_and_invalid_strings_are_rejected(): void
    {
        $proposal = Proposal::factory()->create();
        $tag = Tag::factory()->create();
        $proposal->tags()->attach($tag);
        $this->actingAs($proposal->user, 'sanctum')->post("/api/proposals/{$proposal->id}", [
            '_method' => 'PUT', 'tags' => '[]',
            'file' => UploadedFile::fake()->createWithContent('attachment.pdf', '%PDF-1.4 attachment'),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(0, $proposal->fresh()->tags()->count());
        Storage::disk('public')->assertExists($proposal->fresh()->file_path);

        $this->putJson("/api/proposals/{$proposal->id}", ['tags' => 'not-an-array'])
            ->assertUnprocessable()->assertJsonValidationErrors('tags');
    }

    public function test_review_edits_queue_search_rating_refresh(): void
    {
        $review = Review::factory()->create(['rating' => 4]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/proposals/{$review->proposal_id}/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Updated review',
        ])->assertOk();

        Queue::assertPushed(IndexProposalJob::class, fn ($job) => $job->proposal->id === $review->proposal_id);
        $this->assertEquals(5, $review->proposal->fresh()->toSearchableArray()['average_rating']);
    }

    public function test_proposal_changes_invalidate_all_top_rated_limit_variants(): void
    {
        $proposal = Proposal::factory()->create();
        foreach ([1, 10, 25, 50] as $limit) {
            Cache::put(CacheHelper::topRatedKey($limit), ['stale result'], 900);
        }
        $this->actingAs($proposal->user, 'sanctum')->putJson("/api/proposals/{$proposal->id}", [
            'title' => 'Changed proposal',
        ])->assertOk();

        foreach ([1, 10, 25, 50] as $limit) {
            $this->assertFalse(Cache::has(CacheHelper::topRatedKey($limit)));
        }
    }

    public function test_algolia_outage_falls_back_to_role_scoped_filtered_database_search(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $tag = Tag::factory()->create();
        $matching = Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel match', 'status' => 'approved']);
        $matching->tags()->attach($tag);
        Proposal::factory()->create(['title' => 'Laravel other user', 'status' => 'approved'])->tags()->attach($tag);
        Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel wrong status', 'status' => 'pending'])->tags()->attach($tag);
        Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel wrong tag', 'status' => 'approved']);
        config(['scout.driver' => 'algolia', 'scout.algolia.id' => 'TESTAPP123', 'scout.algolia.secret' => 'test-admin-key']);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('paginate')->once()->andThrow(new UnreachableException);
        app(EngineManager::class)->extend('algolia', fn () => $engine);

        $response = $this->actingAs($speaker, 'sanctum')->getJson("/api/proposals?search=Laravel&status=approved&tags={$tag->id}&per_page=1");
        $response->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->assertSame([$matching->id], array_column($response->json('data.proposals'), 'id'));
    }

    public function test_scout_receives_supported_filter_options(): void
    {
        $speaker = User::factory()->create(['role' => 'speaker']);
        $tag = Tag::factory()->create();
        $proposal = Proposal::factory()->create(['user_id' => $speaker->id, 'title' => 'Laravel match', 'status' => 'approved']);
        $proposal->tags()->attach($tag);
        config(['scout.driver' => 'algolia', 'scout.algolia.id' => 'TESTAPP123', 'scout.algolia.secret' => 'test-admin-key']);
        $engine = Mockery::mock(Engine::class);
        $engine->shouldReceive('paginate')->once()->withArgs(function ($builder, $perPage, $page) use ($speaker, $tag): bool {
            return $perPage === 1 && $page === 1
                && str_contains($builder->options['filters'], 'user_id:'.$speaker->id)
                && str_contains($builder->options['filters'], 'status:approved')
                && str_contains($builder->options['filters'], 'tag_ids:'.$tag->id);
        })->andReturn(['nbHits' => 1]);
        $engine->shouldReceive('map')->once()->andReturn($proposal->newCollection([$proposal]));
        $engine->shouldReceive('getTotalCount')->once()->andReturn(1);
        app(EngineManager::class)->extend('algolia', fn () => $engine);

        $this->actingAs($speaker, 'sanctum')->getJson("/api/proposals?search=Laravel&status=approved&tags={$tag->id}&per_page=1")
            ->assertOk()->assertJsonPath('data.proposals.0.id', $proposal->id);
    }
}
