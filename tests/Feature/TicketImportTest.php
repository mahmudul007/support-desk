<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessTicketImport;
use App\Models\Category;
use App\Models\ImportRun;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Import\TicketImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_import_portal(): void
    {
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($customer)->get(route('imports.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('imports.index'))->assertForbidden();
    }

    public function test_import_processes_valid_rows_skips_duplicates_and_captures_errors(): void
    {
        Storage::fake('local');
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $hardwareCat = Category::factory()->create(['name' => 'Hardware']);

        // Pre-create an existing ticket with external_ref to test deduplication
        Ticket::factory()->create(['external_ref' => 'REF-EXISTING']);

        // Construct CSV content
        $csvContent = implode("\n", [
            'subject,description,priority,category,external_ref',
            'Valid Ticket One,Valid description text,high,Hardware,REF-001',
            'Duplicate Ticket,Should be skipped,low,Hardware,REF-EXISTING',
            ',Missing subject so this is invalid,critical,Hardware,REF-002',
            'Valid Ticket Two,Another valid description,critical,Hardware,REF-003',
        ]);

        $file = UploadedFile::fake()->createWithContent('test_tickets.csv', $csvContent);

        $response = $this->actingAs($admin)
            ->post(route('imports.store'), [
                'file' => $file,
            ]);

        Queue::assertPushed(ProcessTicketImport::class);

        $importRun = ImportRun::first();
        $this->assertNotNull($importRun);
        $response->assertRedirect(route('imports.show', $importRun));

        // Process import directly using service
        $service = app(TicketImportService::class);
        $service->process($importRun);

        $importRun->refresh();

        $this->assertSame(ImportStatus::Completed, $importRun->status);
        $this->assertSame(4, $importRun->total_rows);
        $this->assertSame(2, $importRun->success_rows); // 2 valid tickets
        $this->assertSame(1, $importRun->duplicate_rows); // 1 duplicate skipped
        $this->assertSame(1, $importRun->failed_rows); // 1 validation error

        // Check tickets in database
        $this->assertDatabaseHas('tickets', [
            'external_ref' => 'REF-001',
            'subject' => 'Valid Ticket One',
            'category_id' => $hardwareCat->id,
        ]);
        $this->assertDatabaseHas('tickets', [
            'external_ref' => 'REF-003',
            'subject' => 'Valid Ticket Two',
        ]);

        // Check error table logged the invalid row
        $this->assertDatabaseHas('import_run_errors', [
            'import_run_id' => $importRun->id,
            'row_number' => 3,
        ]);
    }

    public function test_error_csv_can_be_downloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $importRun = ImportRun::factory()->create(['failed_rows' => 2]);

        $importRun->errors()->create([
            'row_number' => 5,
            'raw_data' => ['subject' => ''],
            'errors' => ['subject' => ['The subject field is required.']],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('imports.errors', $importRun));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=import_errors_'.$importRun->id.'.csv');
    }
}
