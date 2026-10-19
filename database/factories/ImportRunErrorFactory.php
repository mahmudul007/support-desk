<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportRun;
use App\Models\ImportRunError;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRunError>
 */
class ImportRunErrorFactory extends Factory
{
    protected $model = ImportRunError::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'row_number' => fake()->numberBetween(1, 100),
            'raw_data' => [
                'subject' => '',
                'description' => fake()->sentence(),
                'priority' => 'invalid_priority',
                'category' => fake()->word(),
            ],
            'errors' => [
                'subject' => ['The subject field is required.'],
                'priority' => ['The selected priority is invalid.'],
            ],
            'created_at' => now(),
        ];
    }
}
