<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketActivity>
 */
class TicketActivityFactory extends Factory
{
    protected $model = TicketActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'type' => ActivityType::Created,
            'old_value' => null,
            'new_value' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate status changed.
     */
    public function statusChanged(string $from, string $to): static
    {
        return $this->state(fn () => [
            'type' => ActivityType::StatusChanged,
            'old_value' => $from,
            'new_value' => $to,
        ]);
    }

    /**
     * Indicate priority changed.
     */
    public function priorityChanged(string $from, string $to): static
    {
        return $this->state(fn () => [
            'type' => ActivityType::PriorityChanged,
            'old_value' => $from,
            'new_value' => $to,
        ]);
    }

    /**
     * Indicate assigned to an agent.
     */
    public function assigned(string $agentName): static
    {
        return $this->state(fn () => [
            'type' => ActivityType::Assigned,
            'new_value' => $agentName,
        ]);
    }
}
