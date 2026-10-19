<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_admin_with_aggregate_kpis(): void
    {
        $admin = User::factory()->admin()->create();

        // Seed diverse tickets
        Ticket::factory()->create([
            'status' => TicketStatus::Open,
            'assigned_agent_id' => null,
            'priority' => TicketPriority::Critical,
            'subject' => 'Urgent Server Crash',
        ]);

        Ticket::factory()->create([
            'status' => TicketStatus::InProgress,
            'priority' => TicketPriority::High,
        ]);

        Ticket::factory()->resolved()->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Operational Dashboard');
        $response->assertSee('Urgent Server Crash');
        $response->assertViewHas('kpis');
        $response->assertViewHas('priorityCounts');
        $response->assertViewHas('agentWorkloads');
    }

    public function test_dashboard_is_role_scoped_for_customers(): void
    {
        $customer1 = User::factory()->customer()->create(['name' => 'Alice Customer']);
        $customer2 = User::factory()->customer()->create(['name' => 'Bob Customer']);

        $ticket1 = Ticket::factory()->create([
            'created_by' => $customer1->id,
            'subject' => 'Alice Private Request',
        ]);

        $ticket2 = Ticket::factory()->create([
            'created_by' => $customer2->id,
            'subject' => 'Bob Confidential Issue',
        ]);

        $response = $this->actingAs($customer1)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Alice Private Request');
        $response->assertDontSee('Bob Confidential Issue');
    }
}
