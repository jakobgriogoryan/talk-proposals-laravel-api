<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * File-related constants.
 */
final class FileConstants
{
    /**
     * Maximum file size in kilobytes (4MB).
     */
    public const MAX_FILE_SIZE_KB = 4096;

    /**
     * Maximum file size in bytes.
     */
    public const MAX_FILE_SIZE_BYTES = 4_194_304;

    /**
     * Allowed MIME types for proposal files.
     *
     * @var array<string>
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
    ];

    /**
     * Allowed file extensions.
     *
     * @var array<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf',
    ];

    /**
     * Storage disk for proposal files.
     */
    public const PROPOSAL_STORAGE_DISK = 'public';

    /**
     * Storage path for proposal files.
     */
    public const PROPOSAL_STORAGE_PATH = 'proposals';
}
