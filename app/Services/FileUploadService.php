<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FileConstants;
use App\Exceptions\ProposalFileNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for handling secure file uploads with validation.
 */
final class FileUploadService
{
    /**
     * PDF file signature (magic bytes).
     */
    private const PDF_SIGNATURE = '%PDF';

    /**
     * Store uploaded file after domain-level validation.
     * 
     * Note: Request-level validation (size, MIME type, extension) is handled
     * by Form Request classes. This method only performs domain-level validation
     * (PDF structure, storage quota) and stores the file.
     *
     * @param UploadedFile $file The uploaded file (already validated by Request class)
     * @param int $userId The user ID for quota checking
     * @return string The stored file path
     * @throws \InvalidArgumentException If domain validation fails
     * @throws \RuntimeException If file storage fails
     */
    public function storeAndValidateDomain(UploadedFile $file, int $userId): string
    {
        // Perform domain-level validation only
        $this->validateDomainRules($file, $userId);

        $path = $file->store(FileConstants::PROPOSAL_STORAGE_PATH, FileConstants::PROPOSAL_STORAGE_DISK);

        if (! $path) {
            throw new \RuntimeException('Failed to store file');
        }

        return $path;
    }

    /**
     * Validate domain-level rules for uploaded file.
     * 
     * This method only validates domain/business logic rules:
     * - PDF structure validation (security check)
     * - User storage quota (business rule)
     * 
     * Request-level validation (size, MIME type, extension) is handled
     * by Form Request classes and should not be duplicated here.
     *
     * @param UploadedFile $file The uploaded file (already validated by Request class)
     * @param int $userId The user ID for quota checking
     * @throws \InvalidArgumentException If domain validation fails
     */
    public function validateDomainRules(UploadedFile $file, int $userId): void
    {
        // 1. Validate PDF structure (check magic bytes) - Domain-level security check
        $this->validatePdfStructure($file);

        // 2. Check user storage quota - Domain-level business rule
        $this->checkUserStorageQuota($userId, $file->getSize());
    }

    /**
     * Validate PDF file structure by checking magic bytes.
     *
     * @param UploadedFile $file The uploaded file
     * @throws \InvalidArgumentException If file is not a valid PDF
     */
    private function validatePdfStructure(UploadedFile $file): void
    {
        // Read first 4 bytes to check PDF signature
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw new \InvalidArgumentException('Unable to read file for validation');
        }

        $header = fread($handle, 4);
        fclose($handle);

        if ($header === false || ! str_starts_with($header, self::PDF_SIGNATURE)) {
            throw new \InvalidArgumentException('File does not appear to be a valid PDF document');
        }
    }

    /**
     * Check user storage quota.
     *
     * @param int $userId The user ID
     * @param int $newFileSize The size of the new file in bytes
     * @throws \InvalidArgumentException If quota exceeded
     */
    private function checkUserStorageQuota(int $userId, int $newFileSize): void
    {
        $maxQuota = $this->getUserMaxQuota($userId);
        $currentUsage = $this->getUserStorageUsage($userId);

        if (($currentUsage + $newFileSize) > $maxQuota) {
            $maxQuotaMB = round($maxQuota / 1024 / 1024, 2);
            $currentUsageMB = round($currentUsage / 1024 / 1024, 2);
            throw new \InvalidArgumentException(
                sprintf(
                    'Storage quota exceeded. Current usage: %s MB, Max quota: %s MB',
                    $currentUsageMB,
                    $maxQuotaMB
                )
            );
        }
    }

    /**
     * Get maximum storage quota for user (in bytes).
     *
     * @param int $userId The user ID
     * @return int Maximum quota in bytes (default: 100MB)
     */
    private function getUserMaxQuota(int $userId): int
    {
        // Default quota: 100MB per user
        // Can be customized per user role or via database
        $defaultQuotaMB = (int) config('app.file_storage.quota_per_user_mb', 100);

        return $defaultQuotaMB * 1024 * 1024;
    }

    /**
     * Get current storage usage for user (in bytes).
     *
     * @param int $userId The user ID
     * @return int Current usage in bytes
     */
    private function getUserStorageUsage(int $userId): int
    {
        $disk = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK);
        $path = FileConstants::PROPOSAL_STORAGE_PATH;

        // Get all files for this user's proposals
        // Note: This assumes file paths are stored in proposals table
        // For better performance, consider caching this value
        $totalSize = 0;

        try {
            $files = \App\Models\Proposal::where('user_id', $userId)
                ->whereNotNull('file_path')
                ->pluck('file_path');

            foreach ($files as $filePath) {
                if ($disk->exists($filePath)) {
                    $totalSize += $disk->size($filePath);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error calculating user storage usage', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $totalSize;
    }

    /**
     * Delete file from storage.
     *
     * @param string $filePath The file path to delete
     * @return bool True if deleted, false otherwise
     */
    public function deleteFile(string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $disk = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK);

        if (! $disk->exists($filePath)) {
            Log::warning('Attempted to delete non-existent file', ['file_path' => $filePath]);

            return false;
        }

        return $disk->delete($filePath);
    }

    /**
     * Get file size.
     *
     * @param string $filePath The file path
     * @return int File size in bytes
     * @throws ProposalFileNotFoundException If file doesn't exist
     */
    public function getFileSize(string $filePath): int
    {
        $disk = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK);

        if (! $disk->exists($filePath)) {
            throw new ProposalFileNotFoundException("File not found: {$filePath}");
        }

        return $disk->size($filePath);
    }
}

