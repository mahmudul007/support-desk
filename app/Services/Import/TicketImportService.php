<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\ImportStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\ImportRun;
use App\Models\ImportRunError;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Sla\SlaCalculator;
use App\Services\Tickets\TicketNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SplFileObject;
use Throwable;

class TicketImportService
{
    public function __construct(
        protected TicketNumberGenerator $numberGenerator,
        protected SlaCalculator $slaCalculator,
        protected ActivityLogger $activityLogger,
    ) {}

    /**
     * Process an import run file in memory-safe streaming chunks.
     */
    public function process(ImportRun $importRun): void
    {
        $filePath = Storage::disk('local')->path($importRun->stored_path);

        if (! file_exists($filePath)) {
            $importRun->update([
                'status' => ImportStatus::Failed,
                'failure_reason' => 'Import source file not found on disk.',
            ]);

            return;
        }

        $importRun->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $file = new SplFileObject($filePath, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

            $header = null;
            $chunk = [];
            $rowNumber = 0;

            // Pre-load category lookup map: lowercase name => id
            $categoryMap = Category::query()->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
                ->all();

            // Default creator for imported tickets if no email match
            $adminUser = $importRun->user;

            foreach ($file as $row) {
                if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                    continue;
                }

                if ($header === null) {
                    // Normalize header column names
                    $header = array_map(fn ($col) => strtolower(trim((string) $col)), $row);

                    continue;
                }

                $rowNumber++;

                if (count($row) !== count($header)) {
                    // Malformed row length
                    $this->logRowError($importRun, $rowNumber, ['raw' => $row], ['format' => 'Column count mismatch with header.']);
                    $importRun->increment('failed_rows');
                    $importRun->increment('processed_rows');

                    continue;
                }

                $rowData = array_combine($header, $row);
                $chunk[] = [
                    'row_number' => $rowNumber,
                    'data' => $rowData,
                ];

                if (count($chunk) >= 500) {
                    $this->processChunk($importRun, $chunk, $categoryMap, $adminUser);
                    $chunk = [];
                }
            }

            if (! empty($chunk)) {
                $this->processChunk($importRun, $chunk, $categoryMap, $adminUser);
            }

            $importRun->update([
                'status' => ImportStatus::Completed,
                'finished_at' => now(),
                'total_rows' => $rowNumber,
            ]);
        } catch (Throwable $e) {
            $importRun->update([
                'status' => ImportStatus::Failed,
                'finished_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process a chunk of rows with validation, deduplication, SLA computation, and batch insertion.
     *
     * @param  array<int, array{row_number: int, data: array<string, mixed>}>  $chunk
     * @param  array<string, int>  $categoryMap
     */
    protected function processChunk(
        ImportRun $importRun,
        array $chunk,
        array $categoryMap,
        User $defaultCreator
    ): void {
        $validRows = [];
        $externalRefs = [];

        // 1. Validate each row
        foreach ($chunk as $item) {
            $rowNumber = $item['row_number'];
            $data = $item['data'];

            $validator = Validator::make($data, [
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'priority' => ['required', 'string', 'in:low,medium,high,critical'],
                'external_ref' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', 'in:open,in_progress,waiting_for_customer,resolved,closed'],
            ]);

            if ($validator->fails()) {
                $this->logRowError($importRun, $rowNumber, $data, $validator->errors()->toArray());
                $importRun->increment('failed_rows');
                $importRun->increment('processed_rows');

                continue;
            }

            $externalRef = ! empty($data['external_ref']) ? trim((string) $data['external_ref']) : null;

            if ($externalRef !== null) {
                $externalRefs[$rowNumber] = $externalRef;
            }

            $validRows[$rowNumber] = $data;
        }

        if (empty($validRows)) {
            return;
        }

        // 2. Detect and skip duplicate external_refs already in database
        $existingRefs = [];
        if (! empty($externalRefs)) {
            $existingRefs = Ticket::query()
                ->whereIn('external_ref', array_values($externalRefs))
                ->pluck('external_ref')
                ->all();
        }

        $rowsToInsert = [];
        foreach ($validRows as $rowNumber => $data) {
            $externalRef = $data['external_ref'] ?? null;

            if ($externalRef && in_array($externalRef, $existingRefs, true)) {
                $importRun->increment('duplicate_rows');
                $importRun->increment('processed_rows');

                continue;
            }

            $rowsToInsert[$rowNumber] = $data;
        }

        if (empty($rowsToInsert)) {
            return;
        }

        // 3. Reserve ticket numbers range for this batch
        $count = count($rowsToInsert);
        $ticketNumbers = $this->numberGenerator->reserveRange($count);
        $numberIndex = 0;

        // 4. Build records for batch insertion
        $records = [];
        $now = now();

        foreach ($rowsToInsert as $rowNumber => $data) {
            $ticketNumber = $ticketNumbers[$numberIndex++];
            $priority = TicketPriority::from(strtolower(trim((string) $data['priority'])));
            $status = ! empty($data['status']) ? TicketStatus::from(strtolower(trim((string) $data['status']))) : TicketStatus::Open;

            // Resolve category ID if provided
            $categoryId = null;
            if (! empty($data['category'])) {
                $catKey = strtolower(trim((string) $data['category']));
                $categoryId = $categoryMap[$catKey] ?? null;
            }

            // Calculate SLA deadlines
            $timestamps = $this->slaCalculator->calculateTimestampsForPriority($priority, $now);

            $records[] = [
                'ticket_number' => $ticketNumber,
                'external_ref' => ! empty($data['external_ref']) ? trim((string) $data['external_ref']) : null,
                'subject' => trim((string) $data['subject']),
                'description' => trim((string) $data['description']),
                'priority' => $priority->value,
                'status' => $status->value,
                'category_id' => $categoryId,
                'created_by' => $defaultCreator->id,
                'sla_due_at' => $timestamps['sla_due_at'],
                'sla_warning_at' => $timestamps['sla_warning_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert records within transaction
        DB::transaction(function () use ($records, $importRun, $count): void {
            Ticket::query()->insert($records);
            $importRun->increment('success_rows', $count);
            $importRun->increment('processed_rows', $count);
        });
    }

    /**
     * Log a row validation error to database.
     *
     * @param  array<string, mixed>  $rawData
     * @param  array<string, mixed>  $errors
     */
    protected function logRowError(ImportRun $importRun, int $rowNumber, array $rawData, array $errors): void
    {
        ImportRunError::create([
            'import_run_id' => $importRun->id,
            'row_number' => $rowNumber,
            'raw_data' => $rawData,
            'errors' => $errors,
            'created_at' => now(),
        ]);
    }
}
