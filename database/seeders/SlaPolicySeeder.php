<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    /**
     * Seed SLA policies with business-time resolution targets.
     */
    public function run(): void
    {
        $slaDefinitions = [
            TicketPriority::Critical->value => ['minutes' => 120, 'warning' => 80],
            TicketPriority::High->value => ['minutes' => 480, 'warning' => 80],
            TicketPriority::Medium->value => ['minutes' => 1440, 'warning' => 80],
            TicketPriority::Low->value => ['minutes' => 2880, 'warning' => 80],
        ];

        foreach ($slaDefinitions as $priority => $config) {
            SlaPolicy::firstOrCreate(
                ['priority' => $priority],
                [
                    'resolution_minutes' => $config['minutes'],
                    'warning_threshold_percent' => $config['warning'],
                    'is_active' => true,
                ]
            );
        }
    }
}
