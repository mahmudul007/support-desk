<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with roles, categories, SLA policies, demo accounts, tickets, and import runs.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SlaPolicySeeder::class,
            UserSeeder::class,
            TicketSeeder::class,
            ImportRunSeeder::class,
        ]);
    }
}
