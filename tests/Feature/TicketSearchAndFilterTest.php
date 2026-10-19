<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSearchAndFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_ticket_number_prefix(): void
    {
        $admin = User::factory()->admin()->create();

        $matchTicket = Ticket::factory()->create(['ticket_number' => 'TKT-2026-000123']);
        $otherTicket = Ticket::factory()->create(['ticket_number' => 'TKT-2026-999999']);

        $response = $this->actingAs($admin)
            ->get(route('tickets.index', ['ticket_number' => 'TKT-2026-0001']));

        $response->assertOk();
        $response->assertSee('TKT-2026-000123');
        $response->assertDontSee('TKT-2026-999999');
    }

    public function test_filter_by_keyword_searches_subject_and_description(): void
    {
        $admin = User::factory()->admin()->create();

        $matchingSubject = Ticket::factory()->create(['subject' => 'Outlook Exchange connection timeout']);
        $matchingDesc = Ticket::factory()->create([
            'subject' => 'General inquiry',
            'description' => 'User experienced an exchange failure on mobile.',
        ]);
        $unrelated = Ticket::factory()->create(['subject' => 'Printer jammed']);

        $response = $this->actingAs($admin)
            ->get(route('tickets.index', ['keyword' => 'Exchange']));

        $response->assertOk();
        $response->assertSee('Outlook Exchange connection timeout');
        $response->assertSee('General inquiry');
        $response->assertDontSee('Printer jammed');
    }

    public function test_filter_by_priority_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $networkCat = Category::factory()->create(['name' => 'Network']);
        $hardwareCat = Category::factory()->create(['name' => 'Hardware']);

        $criticalNetwork = Ticket::factory()->create([
            'priority' => TicketPriority::Critical,
            'category_id' => $networkCat->id,
            'subject' => 'Core switch failure',
        ]);

        $criticalHardware = Ticket::factory()->create([
            'priority' => TicketPriority::Critical,
            'category_id' => $hardwareCat->id,
            'subject' => 'Mouse broke',
        ]);

        $response = $this->actingAs($admin)->get(route('tickets.index', [
            'priority' => TicketPriority::Critical->value,
            'category_id' => $networkCat->id,
        ]));

        $response->assertOk();
        $response->assertSee('Core switch failure');
        $response->assertDontSee('Mouse broke');
    }

    public function test_filter_by_sla_status(): void
    {
        $admin = User::factory()->admin()->create();

        $breached = Ticket::factory()->breached()->create(['subject' => 'Overdue server problem']);
        $onTime = Ticket::factory()->create([
            'subject' => 'Fresh inquiry',
            'sla_warning_at' => now()->addHours(2),
            'sla_due_at' => now()->addHours(4),
            'status' => TicketStatus::Open,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('tickets.index', ['sla_status' => 'breached']));

        $response->assertOk();
        $response->assertSee('Overdue server problem');
        $response->assertDontSee('Fresh inquiry');
    }
}
