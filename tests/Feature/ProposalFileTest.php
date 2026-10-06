<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proposal file feature tests.
 */
class ProposalFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test speaker can download own proposal file.
     */
    public function test_speaker_can_download_own_proposal_file(): void
    {
        $speaker = User::factory()->create(['role' => UserRole::SPEAKER->value]);

        $proposal = Proposal::factory()->withSampleAttachment()->create([
            'user_id' => $speaker->id,
        ]);

        // Use get() instead of getJson() for file downloads
        $response = $this->actingAs($speaker, 'sanctum')
            ->get("/api/proposals/{$proposal->id}/download");

        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(file_get_contents(database_path('fixtures/test.pdf')), file_get_contents($response->baseResponse->getFile()->getPathname()));
    }

    /**
     * Test speaker cannot download other speaker's file.
     */
    public function test_speaker_cannot_download_other_speaker_file(): void
    {
        Storage::fake('public');
        $speaker1 = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $speaker2 = User::factory()->create(['role' => UserRole::SPEAKER->value]);
        $file = UploadedFile::fake()->create('proposal.pdf', 100);
        $filePath = $file->store('proposals', 'public');
        $proposal = Proposal::factory()->create([
            'user_id' => $speaker2->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($speaker1, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/download");

        $response->assertStatus(403);
    }

    /**
     * Test reviewer can download any proposal file.
     */
    public function test_reviewer_can_download_any_proposal_file(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);

        $proposal = Proposal::factory()->withSampleAttachment()->create();

        // Use get() instead of getJson() for file downloads
        $response = $this->actingAs($reviewer, 'sanctum')
            ->get("/api/proposals/{$proposal->id}/download");

        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test download returns 404 when file not found.
     */
    public function test_download_returns_404_when_file_not_found(): void
    {
        $user = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create(['file_path' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/proposals/{$proposal->id}/download");

        $response->assertStatus(404);
    }

    public function test_missing_storage_file_returns_404(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::REVIEWER->value]);
        $proposal = Proposal::factory()->create(['file_path' => 'proposals/missing.pdf']);
        $this->actingAs($reviewer, 'sanctum')->getJson("/api/proposals/{$proposal->id}/download")->assertNotFound();
    }

    public function test_download_requires_authentication(): void
    {
        $proposal = Proposal::factory()->withSampleAttachment()->create();
        $this->getJson("/api/proposals/{$proposal->id}/download")->assertUnauthorized();
    }

    public function test_admin_can_download_sample_attachment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $proposal = Proposal::factory()->withSampleAttachment()->create();
        $this->actingAs($admin, 'sanctum')->get("/api/proposals/{$proposal->id}/download")
            ->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
    }
}
