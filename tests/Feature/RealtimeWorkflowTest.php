<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ProposalReviewed;
use App\Events\ProposalStatusChanged;
use App\Events\ProposalSubmitted;
use App\Exceptions\DuplicateReviewException;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RealtimeWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('public');
    }

    public function test_review_broadcast_failure_preserves_success(): void
    {
        Event::listen(ProposalReviewed::class, fn () => throw new RuntimeException('Broadcast enqueue failed'));
        $proposal = Proposal::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", ['rating' => 5])
            ->assertCreated()->assertJsonPath('data.review.rating', 5);
        $this->assertSame(1, Review::count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_status_broadcast_failure_preserves_success(): void
    {
        Event::listen(ProposalStatusChanged::class, fn () => throw new RuntimeException('Broadcast enqueue failed'));
        $proposal = Proposal::factory()->create(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/proposals/{$proposal->id}/status", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('data.proposal.status', 'approved');
        $this->assertSame('approved', $proposal->fresh()->status->value);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_review_cache_failure_does_not_prevent_post_commit_broadcast(): void
    {
        $observed = [];
        $proposal = Proposal::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('Cache unavailable'));
        Event::listen(ProposalReviewed::class, function ($event) use (&$observed): void {
            $observed[] = [DB::transactionLevel(), Review::findOrFail($event->review->id)->rating];
        });
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", ['rating' => 5])->assertCreated();
        $this->assertSame([[0, 5]], $observed);
    }

    public function test_status_cache_failure_does_not_prevent_post_commit_broadcast(): void
    {
        $observed = [];
        $proposal = Proposal::factory()->create(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('Cache unavailable'));
        Event::listen(ProposalStatusChanged::class, function ($event) use (&$observed): void {
            $observed[] = [DB::transactionLevel(), $event->oldStatus, $event->newStatus];
        });
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/proposals/{$proposal->id}/status", ['status' => 'approved'])->assertOk();
        $this->assertSame([[0, 'pending', 'approved']], $observed);
    }

    public function test_submission_cache_failure_does_not_prevent_post_commit_broadcast(): void
    {
        $observed = [];
        $speaker = User::factory()->create(['role' => 'speaker']);
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('Cache unavailable'));
        Event::listen(ProposalSubmitted::class, function ($event) use (&$observed): void {
            $observed[] = [DB::transactionLevel(), Proposal::findOrFail($event->proposal->id)->title];
        });
        $this->actingAs($speaker, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Timing test', 'description' => 'Cache failures must not skip broadcasts.',
            'file' => UploadedFile::fake()->createWithContent('timing.pdf', '%PDF-1.4 timing'),
        ])->assertCreated();
        $this->assertSame([[0, 'Timing test']], $observed);
    }

    public function test_review_commit_callback_failure_still_dispatches(): void
    {
        $observed = 0;
        $proposal = Proposal::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        Review::created(fn () => DB::afterCommit(fn () => throw new RuntimeException('Commit callback failed')));
        Event::listen(ProposalReviewed::class, function () use (&$observed): void {
            $observed++;
        });
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", ['rating' => 5])->assertCreated();
        $this->assertSame(1, Review::count());
        $this->assertSame(1, $observed);
    }

    public function test_submission_commit_callback_failure_still_dispatches(): void
    {
        $observed = 0;
        $speaker = User::factory()->create(['role' => 'speaker']);
        Proposal::created(fn () => DB::afterCommit(fn () => throw new RuntimeException('Commit callback failed')));
        Event::listen(ProposalSubmitted::class, function () use (&$observed): void {
            $observed++;
        });
        $this->actingAs($speaker, 'sanctum')->postJson('/api/proposals', [
            'title' => 'Timing test', 'description' => 'Commit callbacks must not skip broadcasts.',
            'file' => UploadedFile::fake()->createWithContent('timing.pdf', '%PDF-1.4 timing'),
        ])->assertCreated();
        $this->assertSame(1, Proposal::count());
        $this->assertSame(1, $observed);
        Storage::disk('public')->assertExists(Proposal::sole()->file_path);
    }

    public function test_post_commit_domain_exception_is_not_mistaken_for_a_duplicate_write(): void
    {
        $proposal = Proposal::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        Review::created(fn () => DB::afterCommit(fn () => throw new DuplicateReviewException));
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", ['rating' => 5])->assertCreated();
        $this->assertSame(1, Review::count());
    }

    public function test_status_commit_callback_failure_still_dispatches(): void
    {
        $observed = 0;
        $proposal = Proposal::factory()->create(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        Proposal::updated(fn () => DB::afterCommit(fn () => throw new RuntimeException('Commit callback failed')));
        Event::listen(ProposalStatusChanged::class, function () use (&$observed): void {
            $observed++;
        });
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/proposals/{$proposal->id}/status", ['status' => 'approved'])->assertOk();
        $this->assertSame('approved', $proposal->fresh()->status->value);
        $this->assertSame(1, $observed);
    }

    public function test_failed_review_write_rolls_back_without_broadcasting(): void
    {
        $observed = 0;
        $proposal = Proposal::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        Review::created(fn () => throw new RuntimeException('Write failed'));
        Event::listen(ProposalReviewed::class, function () use (&$observed): void {
            $observed++;
        });
        $this->actingAs($reviewer, 'sanctum')->postJson("/api/proposals/{$proposal->id}/reviews", ['rating' => 5])->assertStatus(500);
        $this->assertSame(0, Review::count());
        $this->assertSame(0, $observed);
    }

    public function test_noop_and_failed_status_write_do_not_broadcast(): void
    {
        $observed = 0;
        $proposal = Proposal::factory()->create(['status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);
        Event::listen(ProposalStatusChanged::class, function () use (&$observed): void {
            $observed++;
        });
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/proposals/{$proposal->id}/status", ['status' => 'pending'])->assertOk();
        Proposal::updated(fn () => throw new RuntimeException('Write failed'));
        $this->patchJson("/api/admin/proposals/{$proposal->id}/status", ['status' => 'approved'])->assertStatus(500);
        $this->assertSame('pending', $proposal->fresh()->status->value);
        $this->assertSame(0, $observed);
    }
}
