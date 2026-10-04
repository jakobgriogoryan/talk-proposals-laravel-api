<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Proposal;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test file upload security features.
 */
class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FileUploadService $fileUploadService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->user = User::factory()->create([
            'role' => 'speaker',
        ]);

        $this->fileUploadService = app(FileUploadService::class);
    }

    /**
     * Test valid PDF file upload.
     */
    public function test_valid_pdf_upload(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $file = UploadedFile::fake()->createWithContent('test.pdf', file_get_contents(database_path('fixtures/test.pdf')));

        $response = $this->post('/api/proposals', [
            'title' => 'Test Proposal',
            'description' => 'Test description',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $proposal = Proposal::sole();
        $this->assertSame($this->user->id, $proposal->user_id);
        Storage::disk('public')->assertExists($proposal->file_path);
    }

    /**
     * Test invalid file extension is rejected.
     */
    public function test_invalid_file_extension_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum');

        $file = UploadedFile::fake()->create('test.exe', 100);

        $response = $this->post('/api/proposals', [
            'title' => 'Test Proposal',
            'description' => 'Test description',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        // Form Request validation should catch invalid file extension
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test file size limit enforcement.
     */
    public function test_file_size_limit_enforced(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create a file larger than 4MB (4096 KB)
        // UploadedFile::fake()->create() size is in KB, so 5000 KB = 5MB
        $file = UploadedFile::fake()->create('large.pdf', 5000, 'application/pdf');

        $response = $this->post('/api/proposals', [
            'title' => 'Test Proposal',
            'description' => 'Test description',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        // Form Request validation should catch file size limit
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test MIME type validation.
     */
    public function test_mime_type_validation(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // Create a file with wrong MIME type (PDF extension but JPEG MIME)
        $file = UploadedFile::fake()->create('test.pdf', 100, 'image/jpeg');

        $response = $this->post('/api/proposals', [
            'title' => 'Test Proposal',
            'description' => 'Test description',
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        // Form Request validation should catch invalid MIME type
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    /**
     * Test PDF structure validation.
     */
    public function test_pdf_structure_validation(): void
    {
        // Create a file that looks like PDF but isn't
        $file = UploadedFile::fake()->createWithContent('fake.pdf', 'This is not a PDF file');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File does not appear to be a valid PDF document');

        $this->fileUploadService->validateDomainRules($file, $this->user->id);
    }

    /**
     * Test user storage quota enforcement.
     */
    public function test_user_storage_quota_enforced(): void
    {
        // Set a low quota for testing (5MB = 5 * 1024 * 1024 bytes)
        Config::set('app.file_storage.quota_per_user_mb', 5);

        // Use fake storage
        Storage::fake('public');

        // Create existing proposals with files that total 4.5MB (90% of 5MB quota)
        // This leaves only 0.5MB available
        // 2MB = 2 * 1024 * 1024 = 2,097,152 bytes
        $file1Size = 2_097_152; // 2MB
        $file2Size = 2_097_152; // 2MB
        $file3Size = 524_288;   // 0.5MB

        // Define file paths
        $filePath1 = 'proposals/existing1.pdf';
        $filePath2 = 'proposals/existing2.pdf';
        $filePath3 = 'proposals/existing3.pdf';

        // Put files in storage
        Storage::disk('public')->put($filePath1, str_repeat('x', $file1Size));
        Storage::disk('public')->put($filePath2, str_repeat('x', $file2Size));
        Storage::disk('public')->put($filePath3, str_repeat('x', $file3Size));

        // Create proposals with these file paths
        Proposal::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => $filePath1,
        ]);
        Proposal::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => $filePath2,
        ]);
        Proposal::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => $filePath3,
        ]);

        // Verify total usage is 4.5MB
        $totalUsage = $file1Size + $file2Size + $file3Size; // 4,718,592 bytes = 4.5MB
        $quota = 5 * 1024 * 1024; // 5,242,880 bytes = 5MB
        $available = $quota - $totalUsage; // 524,288 bytes = 0.5MB

        // Verify the service can see the existing files
        // This ensures our test setup is correct
        $disk = Storage::disk('public');
        $this->assertTrue($disk->exists($filePath1), "File 1 should exist at: {$filePath1}");
        $this->assertTrue($disk->exists($filePath2), "File 2 should exist at: {$filePath2}");
        $this->assertTrue($disk->exists($filePath3), "File 3 should exist at: {$filePath3}");

        // Verify file sizes
        $this->assertEquals($file1Size, $disk->size($filePath1), 'File 1 size mismatch');
        $this->assertEquals($file2Size, $disk->size($filePath2), 'File 2 size mismatch');
        $this->assertEquals($file3Size, $disk->size($filePath3), 'File 3 size mismatch');

        // Try to upload a file that exceeds the remaining quota
        // Create a file larger than available space (1MB > 0.5MB available)
        // Create file content with PDF magic bytes + enough data to exceed quota
        $newFileSize = 1_048_576; // 1MB (exceeds 0.5MB available)
        $fileContent = '%PDF-1.4 '.str_repeat('x', $newFileSize - 10); // Ensure total is ~1MB
        $file = UploadedFile::fake()->createWithContent('large.pdf', $fileContent);

        // Verify the file size exceeds available quota
        $actualFileSize = $file->getSize();
        $this->assertGreaterThan($available, $actualFileSize,
            sprintf('File size (%d bytes) should exceed available quota (%d bytes)', $actualFileSize, $available));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage quota exceeded');

        $this->fileUploadService->validateDomainRules($file, $this->user->id);
    }

    /**
     * Test user storage quota allows upload when within limit.
     */
    public function test_user_storage_quota_allows_upload_within_limit(): void
    {
        // Set a quota for testing (10MB)
        Config::set('app.file_storage.quota_per_user_mb', 10);

        // Use fake storage
        Storage::fake('public');

        // Create existing proposal with 2MB file
        $existingFile = 'proposals/existing.pdf';
        $this->assertTrue(Storage::disk('public')->put($existingFile, str_repeat('x', 2_097_152)));

        Proposal::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => $existingFile,
        ]);

        // Total usage: 2MB, Quota: 10MB, Available: 8MB

        // Upload a file that fits within quota (3MB < 8MB available)
        $file = UploadedFile::fake()->createWithContent('test.pdf', '%PDF-1.4 '.str_repeat('x', 3_145_728)); // 3MB

        // Should not throw exception
        try {
            $this->fileUploadService->validateDomainRules($file, $this->user->id);
            $this->assertTrue(true, 'Quota validation passed');
        } catch (\InvalidArgumentException $e) {
            $this->fail('Quota validation should pass but threw: '.$e->getMessage());
        }
    }

    public function test_storage_quota_counts_existing_files_and_accepts_the_exact_boundary(): void
    {
        Config::set('app.file_storage.quota_per_user_mb', 5);
        $path = 'proposals/existing.pdf';
        Storage::disk('public')->put($path, str_repeat('x', 2_097_152));
        Proposal::factory()->create(['user_id' => $this->user->id, 'file_path' => $path]);
        $content = "%PDF-1.4\n".str_repeat('x', 3_145_728 - 9);
        $atLimit = UploadedFile::fake()->createWithContent('at-limit.pdf', $content);
        $this->assertSame(3_145_728, $atLimit->getSize());
        $this->fileUploadService->validateDomainRules($atLimit, $this->user->id);
        $this->assertSame(2_097_152, Storage::disk('public')->size($path));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Storage quota exceeded');
        $this->fileUploadService->validateDomainRules(
            UploadedFile::fake()->createWithContent('over-limit.pdf', $content.'x'), $this->user->id
        );
    }
}
