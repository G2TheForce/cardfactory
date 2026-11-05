<?php

namespace App\Console\Commands;

use App\Models\CustomerImport;
use App\Services\BatchGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneImports extends Command
{
    protected $signature = 'imports:prune {--days= : Delete unfinished imports older than this many days}';

    protected $description = 'Delete bulk imports that were never generated, with their uploaded spreadsheets';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('smartcard.imports.retention_days', 7));
        $count = 0;

        CustomerImport::whereIn('status', ['uploaded', 'validated'])->where('updated_at', '<', now()->subDays($days))
            ->chunkById(200, function ($imports) use (&$count) {
                foreach ($imports as $import) {
                    if ($import->file_path) {
                        Storage::disk(BatchGenerationService::DISK)->deleteDirectory(dirname($import->file_path));
                    }
                    $import->delete();
                    $count++;
                }
            });

        // Generation that never finished (worker crashed) is reported as failed.
        CustomerImport::where('status', 'processing')->where('updated_at', '<', now()->subHours(2))
            ->update(['status' => 'failed']);

        $this->info("Removed {$count} unfinished import(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
