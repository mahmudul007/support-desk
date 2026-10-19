<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunError;
use App\Models\User;
use Illuminate\Database\Seeder;

class ImportRunSeeder extends Seeder
{
    /**
     * Seed a demo completed import run with sample validation errors.
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin) {
            return;
        }

        $importRun = ImportRun::firstOrCreate(
            ['original_filename' => 'legacy_zendesk_migration_batch_01.csv'],
            [
                'user_id' => $admin->id,
                'stored_path' => 'imports/demo_sample_run.csv',
                'status' => ImportStatus::Completed,
                'total_rows' => 125,
                'processed_rows' => 125,
                'success_rows' => 118,
                'duplicate_rows' => 4,
                'failed_rows' => 3,
                'started_at' => now()->subHours(2),
                'finished_at' => now()->subHours(2)->addSeconds(42),
                'created_at' => now()->subHours(2),
            ]
        );

        // Seed 3 realistic error rows for the demo run
        $errors = [
            [
                'row_number' => 14,
                'raw_data' => [
                    'subject' => '',
                    'description' => 'User could not load payment methods in browser',
                    'priority' => 'high',
                    'category' => 'Billing & Accounts',
                    'external_ref' => 'ZD-4819',
                ],
                'errors' => [
                    'subject' => ['The subject field is required.'],
                ],
            ],
            [
                'row_number' => 58,
                'raw_data' => [
                    'subject' => 'Database export request',
                    'description' => 'Customer requested full GDPR data dump',
                    'priority' => 'urgent', // invalid priority enum
                    'category' => 'Security & Compliance',
                    'external_ref' => 'ZD-5102',
                ],
                'errors' => [
                    'priority' => ['The selected priority is invalid. Must be low, medium, high, or critical.'],
                ],
            ],
            [
                'row_number' => 93,
                'raw_data' => [
                    'subject' => 'Malformed row entry',
                    'description' => '',
                    'priority' => 'low',
                    'category' => 'General Inquiries',
                    'external_ref' => 'ZD-5421',
                ],
                'errors' => [
                    'description' => ['The description field is required.'],
                ],
            ],
        ];

        foreach ($errors as $error) {
            ImportRunError::firstOrCreate(
                [
                    'import_run_id' => $importRun->id,
                    'row_number' => $error['row_number'],
                ],
                [
                    'raw_data' => $error['raw_data'],
                    'errors' => $error['errors'],
                    'created_at' => now()->subHours(2),
                ]
            );
        }
    }
}
