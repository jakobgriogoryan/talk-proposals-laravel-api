<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\SampleProposalFilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class SampleProposalFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_default_factory_has_no_nonexistent_attachment(): void
    {
        $this->assertNull(Proposal::factory()->create()->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_sample_attachments_are_valid_and_independent(): void
    {
        [$first, $second] = Proposal::factory()->withSampleAttachment()->count(2)->create()->all();
        $this->assertNotSame($first->file_path, $second->file_path);
        foreach ([$first, $second] as $proposal) {
            $this->assertSame(file_get_contents(database_path('fixtures/test.pdf')), Storage::disk('public')->get($proposal->file_path));
            $this->assertStringStartsWith('%PDF-', Storage::disk('public')->get($proposal->file_path));
        }
        Storage::disk('public')->delete($first->file_path);
        Storage::disk('public')->assertExists($second->file_path);
    }

    public function test_repair_only_fills_missing_legacy_placeholders_and_is_idempotent(): void
    {
        $legacy = Proposal::factory()->create(['file_path' => 'proposals/12345678-1234-1234-1234-123456789abc.pdf']);
        $existing = Proposal::factory()->withSampleAttachment()->create();
        Storage::disk('public')->put($existing->file_path, 'existing upload - do not overwrite');
        $without = Proposal::factory()->create();
        $other = Proposal::factory()->create(['file_path' => 'proposals/real-upload-missing.pdf']);
        $before = Proposal::orderBy('id')->get()->toArray();

        $this->seed(SampleProposalFilesSeeder::class);
        $this->seed(SampleProposalFilesSeeder::class);

        $this->assertSame(file_get_contents(database_path('fixtures/test.pdf')), Storage::disk('public')->get($legacy->file_path));
        $this->assertSame('existing upload - do not overwrite', Storage::disk('public')->get($existing->file_path));
        Storage::disk('public')->assertMissing($other->file_path);
        $this->assertNull($without->fresh()->file_path);
        $this->assertSame($before, Proposal::orderBy('id')->get()->toArray());
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_database_seeder_creates_real_demo_attachments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $proposals = Proposal::all();
        $this->assertCount(20, $proposals);
        foreach ($proposals as $proposal) {
            Storage::disk('public')->assertExists($proposal->file_path);
            $this->assertSame(file_get_contents(database_path('fixtures/test.pdf')), Storage::disk('public')->get($proposal->file_path));
        }
    }

    public function test_review_seeder_creates_real_attachments_for_missing_demo_proposals(): void
    {
        $this->seed(ReviewSeeder::class);
        $this->assertSame(10, Proposal::count());
        foreach (Proposal::all() as $proposal) {
            Storage::disk('public')->assertExists($proposal->file_path);
        }
    }

    public function test_sample_factory_refuses_production_before_creating_any_rows(): void
    {
        $this->app['env'] = 'production';
        try {
            Proposal::factory()->withSampleAttachment()->create();
            $this->fail('Sample factory must reject production.');
        } catch (LogicException) {
            $this->assertSame(0, Proposal::count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_repair_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(LogicException::class);
        $this->seed(SampleProposalFilesSeeder::class);
    }
}
