<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\FileConstants;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

final class SampleProposalAttachment
{
    public static function store(string $path): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Sample proposal attachments are only available in local and testing environments.');
        }

        $disk = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK);
        if ($disk->exists($path)) {
            return;
        }

        $content = file_get_contents(database_path('fixtures/test.pdf'));
        if ($content === false || ! $disk->put($path, $content)) {
            throw new RuntimeException('Unable to store the sample proposal attachment.');
        }
    }
}
