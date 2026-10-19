<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ImportRun;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_roles_categories_sla_and_dummy_tickets(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Verify users
        $admin = User::where('email', 'admin@supportdesk.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);

        $agent1 = User::where('email', 'agent1@supportdesk.test')->first();
        $this->assertNotNull($agent1);
        $this->assertSame(UserRole::Agent, $agent1->role);
        $this->assertTrue($agent1->categories()->count() > 0);

        $customer = User::where('email', 'customer1@supportdesk.test')->first();
        $this->assertNotNull($customer);
        $this->assertSame(UserRole::Customer, $customer->role);

        // Verify categories & SLA
        $this->assertGreaterThanOrEqual(4, Category::count());
        $this->assertSame(4, SlaPolicy::count());

        // Verify dummy tickets, replies, activities
        $this->assertGreaterThanOrEqual(8, Ticket::count());
        $this->assertTrue(Ticket::whereNotNull('assigned_agent_id')->exists());
        $this->assertTrue(Ticket::whereNull('assigned_agent_id')->exists());
        $this->assertTrue(Ticket::whereHas('replies')->exists());
        $this->assertTrue(Ticket::whereHas('activities')->exists());

        // Verify demo import run
        $this->assertTrue(ImportRun::whereHas('errors')->exists());
    }
}
