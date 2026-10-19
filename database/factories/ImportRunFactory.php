<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'original_filename' => 'tickets_batch_'.fake()->numberBetween(1, 100).'.csv',
            'stored_path' => 'imports/'.fake()->uuid().'.csv',
            'status' => ImportStatus::Completed,
            'total_rows' => 100,
            'processed_rows' => 100,
            'success_rows' => 95,
            'failed_rows' => 3,
            'duplicate_rows' => 2,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
            'failure_reason' => null,
        ];
    }
}
