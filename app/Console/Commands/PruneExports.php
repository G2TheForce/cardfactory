<?php

namespace App\Console\Commands;

use App\Models\CardExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExports extends Command
{
    protected $signature = 'exports:prune {--days= : Delete export files older than this many days}';

    protected $description = 'Delete old export files and mark them as expired';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('smartcard.export.retention_days', 7));
        $count = 0;

        CardExport::whereNotNull('file_path')->where('created_at', '<', now()->subDays($days))
            ->chunkById(200, function ($exports) use (&$count) {
                foreach ($exports as $export) {
                    Storage::disk($export->disk)->deleteDirectory(dirname($export->file_path));
                    $export->update(['file_path' => null, 'status' => $export->status === 'completed' ? 'expired' : $export->status]);
                    $count++;
                }
            });

        // Exports stuck in the queue for a day are reported as failed.
        CardExport::whereIn('status', ['queued', 'processing'])->where('created_at', '<', now()->subDay())
            ->update(['status' => 'failed', 'error' => 'The export timed out.']);

        $this->info("Removed {$count} export file(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
