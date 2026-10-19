<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\Import\TicketImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTicketImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout for large CSV imports: 1 hour.
     */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public ImportRun $importRun
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TicketImportService $importService): void
    {
        $importService->process($this->importRun);
    }
}
