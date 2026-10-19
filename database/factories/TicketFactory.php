<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $priority = fake()->randomElement(TicketPriority::cases());

        return [
            'ticket_number' => 'TKT-'.date('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'external_ref' => null,
            'subject' => fake()->sentence(5),
            'description' => fake()->paragraphs(2, true),
            'priority' => $priority,
            'status' => TicketStatus::Open,
            'category_id' => Category::factory(),
            'created_by' => User::factory()->customer(),
            'assigned_agent_id' => null,
            'assigned_at' => null,
            'sla_due_at' => now()->addMinutes($priority->defaultResolutionMinutes()),
            'sla_warning_at' => now()->addMinutes((int) ($priority->defaultResolutionMinutes() * 0.8)),
            'approaching_notified_at' => null,
            'breached_at' => null,
            'escalated_at' => null,
            'escalation_count' => 0,
            'resolved_at' => null,
            'closed_at' => null,
            'version' => 0,
        ];
    }

    /**
     * Indicate ticket is assigned to an agent.
     */
    public function assigned(?User $agent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_agent_id' => $agent?->id ?? User::factory()->agent(),
            'assigned_at' => now(),
            'status' => TicketStatus::InProgress,
        ]);
    }

    /**
     * Indicate ticket is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Indicate ticket is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Closed,
            'resolved_at' => now()->subDay(),
            'closed_at' => now(),
        ]);
    }

    /**
     * Indicate ticket has breached SLA.
     */
    public function breached(): static
    {
        return $this->state(fn (array $attributes) => [
            'sla_due_at' => now()->subHour(),
            'sla_warning_at' => now()->subHours(5),
            'status' => TicketStatus::Open,
        ]);
    }

    /**
     * Indicate ticket is in waiting_for_customer status.
     */
    public function waitingForCustomer(?User $agent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_agent_id' => $agent?->id ?? User::factory()->agent(),
            'assigned_at' => now()->subHours(2),
            'status' => TicketStatus::WaitingForCustomer,
        ]);
    }

    /**
     * Indicate ticket is escalated due to SLA breach.
     */
    public function escalated(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'sla_due_at' => now()->subMinutes(30),
            'sla_warning_at' => now()->subHours(2),
            'breached_at' => now()->subMinutes(25),
            'escalated_at' => now()->subMinutes(20),
            'escalation_count' => $count,
            'status' => TicketStatus::Open,
        ]);
    }

    /**
     * Indicate ticket SLA is approaching warning threshold.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'sla_due_at' => now()->addMinutes(20),
            'sla_warning_at' => now()->subMinutes(10),
            'approaching_notified_at' => now()->subMinutes(5),
            'status' => TicketStatus::InProgress,
        ]);
    }

    /**
     * Indicate ticket priority.
     */
    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
            'sla_due_at' => now()->addMinutes($priority->defaultResolutionMinutes()),
            'sla_warning_at' => now()->addMinutes((int) ($priority->defaultResolutionMinutes() * 0.8)),
        ]);
    }
}
