<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Proposal;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process proposal file upload asynchronously.
 * 
 * This job handles domain-level validation and storage of proposal files
 * in the background to avoid blocking the HTTP request.
 */
class ProcessProposalFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Proposal $proposal,
        public string $filePath,
        public int $userId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(FileUploadService $fileUploadService): void
    {
        try {
            // The file has already been stored by the controller
            // This job performs domain-level validation (PDF structure, quota)
            // Note: File is already in storage, so we validate the stored file
            
            // Get the file from storage
            $disk = \Illuminate\Support\Facades\Storage::disk(\App\Constants\FileConstants::PROPOSAL_STORAGE_DISK);
            
            if (!$disk->exists($this->filePath)) {
                throw new \RuntimeException("File not found at path: {$this->filePath}");
            }

            // Validate domain rules using the stored file
            // We need to create an UploadedFile instance from the stored file
            $fullPath = $disk->path($this->filePath);
            $file = new \Illuminate\Http\UploadedFile(
                $fullPath,
                basename($this->filePath),
                mime_content_type($fullPath) ?: 'application/pdf',
                null,
                true // test mode
            );

            // Perform domain-level validation
            $fileUploadService->validateDomainRules($file, $this->userId);

            // If validation passes, the file is already stored and valid
            // Update proposal to mark file as processed
            $this->proposal->update([
                'file_path' => $this->filePath,
            ]);

            Log::info('Proposal file processed successfully', [
                'proposal_id' => $this->proposal->id,
                'file_path' => $this->filePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process proposal file', [
                'proposal_id' => $this->proposal->id,
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
            ]);

            // Clean up the file if validation fails
            try {
                $disk = \Illuminate\Support\Facades\Storage::disk(\App\Constants\FileConstants::PROPOSAL_STORAGE_DISK);
                if ($disk->exists($this->filePath)) {
                    $disk->delete($this->filePath);
                }
            } catch (\Exception $cleanupException) {
                Log::warning('Failed to cleanup file after job failure', [
                    'file_path' => $this->filePath,
                    'error' => $cleanupException->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessProposalFileJob failed permanently', [
            'proposal_id' => $this->proposal->id,
            'file_path' => $this->filePath,
            'error' => $exception->getMessage(),
        ]);

        // Clean up the file on permanent failure
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk(\App\Constants\FileConstants::PROPOSAL_STORAGE_DISK);
            if ($disk->exists($this->filePath)) {
                $disk->delete($this->filePath);
            }
        } catch (\Exception $cleanupException) {
            Log::warning('Failed to cleanup file after permanent job failure', [
                'file_path' => $this->filePath,
                'error' => $cleanupException->getMessage(),
            ]);
        }
    }
}

