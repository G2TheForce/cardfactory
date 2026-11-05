<?php

namespace App\Jobs;

use App\Models\CustomerImport;
use App\Services\BatchGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Generates customers, card designs and profiles for a bulk import (queue "exports"). */
class ProcessCustomerImport implements ShouldQueue
{
    use Queueable;

    // Never run twice: rows already generated would be created again.
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public CustomerImport $import)
    {
        $this->onQueue('exports');
    }

    public function handle(BatchGenerationService $service): void
    {
        $service->process($this->import);
    }

    public function failed(\Throwable $e): void
    {
        $this->import->update([
            'status' => 'failed',
            'errors' => array_merge($this->import->errors ?? [], [[
                'line' => 0, 'field' => null, 'column' => null, 'level' => 'error',
                'message' => 'Generation stopped after '.$this->import->processed_rows.' rows. Cards created so far are kept.',
            ]]),
        ]);
    }
}
