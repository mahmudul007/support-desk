<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ActivityType;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new ActivityLogger;
    }

    public function test_log_creates_activity_with_actor_and_diff(): void
    {
        $ticket = Ticket::factory()->create();
        $agent = User::factory()->agent()->create();

        $activity = $this->logger->log(
            ticket: $ticket,
            type: ActivityType::StatusChanged,
            actor: $agent,
            old: 'open',
            new: 'in_progress',
            metadata: ['ip' => '127.0.0.1']
        );

        $this->assertDatabaseHas('ticket_activities', [
            'id' => $activity->id,
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'type' => ActivityType::StatusChanged->value,
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);

        $this->assertSame(['ip' => '127.0.0.1'], $activity->metadata);
    }

    public function test_log_handles_automated_system_actions_without_actor(): void
    {
        $ticket = Ticket::factory()->create();

        $activity = $this->logger->log(
            ticket: $ticket,
            type: ActivityType::Escalated,
            actor: null,
            old: '0',
            new: '1',
            metadata: ['reason' => 'SLA resolution deadline breached']
        );

        $this->assertNull($activity->user_id);
        $this->assertSame(ActivityType::Escalated, $activity->type);
        $this->assertSame('SLA resolution deadline breached', $activity->metadata['reason']);
    }
}
