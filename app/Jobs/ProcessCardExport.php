<?php

namespace App\Jobs;

use App\Models\CardExport;
use App\Services\CardExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Renders one card export in the background (queue "exports"). */
class ProcessCardExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    // Bulk exports of many cards can take a while (keep queue retry_after above this).
    public int $timeout = 3600;

    public function __construct(public CardExport $export)
    {
        $this->onQueue('exports');
    }

    public function handle(CardExportService $service): void
    {
        $service->process($this->export);
    }

    public function failed(\Throwable $e): void
    {
        $this->export->update(['status' => 'failed', 'error' => 'The export could not be completed. Please try again.']);
    }
}
