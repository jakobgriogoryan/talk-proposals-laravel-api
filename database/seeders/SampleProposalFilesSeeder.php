<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\FileConstants;
use App\Models\Proposal;
use Database\Factories\SampleProposalAttachment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use LogicException;

/** Repair only missing legacy factory attachments; do not change proposal data. */
class SampleProposalFilesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Sample file repair is only available in local and testing environments.');
        }

        $disk = Storage::disk(FileConstants::PROPOSAL_STORAGE_DISK);
        $created = 0;
        Proposal::query()->whereNotNull('file_path')->select(['id', 'file_path'])
            ->chunkById(100, function ($proposals) use ($disk, &$created): void {
                foreach ($proposals as $proposal) {
                    if (preg_match('/^proposals\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$/D', $proposal->file_path)
                        && ! $disk->exists($proposal->file_path)) {
                        SampleProposalAttachment::store($proposal->file_path);
                        $created++;
                    }
                }
            });

        $this->command?->info("Created {$created} missing sample PDF attachments; existing files and proposal data were preserved.");
    }
}
