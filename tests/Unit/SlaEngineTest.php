<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Sla\BusinessHours;
use App\Services\Sla\SlaCalculator;
use App\Services\Sla\SlaStatusResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SlaEngineTest extends TestCase
{
    private BusinessHours $businessHours;

    private SlaCalculator $calculator;

    private SlaStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard test calendar: Sunday(0) to Thursday(4), 09:00 to 18:00 Asia/Dhaka
        $this->businessHours = new BusinessHours(
            timezone: 'Asia/Dhaka',
            businessDays: [0, 1, 2, 3, 4],
            startTime: '09:00',
            endTime: '18:00',
        );

        $this->calculator = new SlaCalculator($this->businessHours);
        $this->resolver = new SlaStatusResolver;
    }

    public function test_critical_ticket_created_thursday_at_5pm_is_due_sunday_at_10am(): void
    {
        // 2026-10-22 is Thursday. 17:00 in Asia/Dhaka
        $created = CarbonImmutable::parse('2026-10-22 17:00:00', 'Asia/Dhaka');

        // 2 business hours = 120 minutes
        $dueAt = $this->calculator->calculateDueAt($created, 120);

        // Convert dueAt back to Asia/Dhaka to verify human local expectation
        $dueLocal = $dueAt->setTimezone('Asia/Dhaka');

        // 2026-10-25 is Sunday
        $this->assertSame('2026-10-25 10:00:00', $dueLocal->format('Y-m-d H:i:s'));
        $this->assertSame(0, $dueLocal->dayOfWeek); // Sunday
    }

    public function test_ticket_created_on_weekend_starts_clock_at_sunday_opening(): void
    {
        // 2026-10-23 is Friday (weekend) at 14:00
        $created = CarbonImmutable::parse('2026-10-23 14:00:00', 'Asia/Dhaka');

        // 2 hours = 120 min
        $dueAt = $this->calculator->calculateDueAt($created, 120);
        $dueLocal = $dueAt->setTimezone('Asia/Dhaka');

        $this->assertSame('2026-10-25 11:00:00', $dueLocal->format('Y-m-d H:i:s'));
    }

    public function test_ticket_created_at_exact_closing_boundary_starts_next_business_day(): void
    {
        // Thursday 18:00:00 (closing time)
        $created = CarbonImmutable::parse('2026-10-22 18:00:00', 'Asia/Dhaka');

        $dueAt = $this->calculator->calculateDueAt($created, 60);
        $dueLocal = $dueAt->setTimezone('Asia/Dhaka');

        // Opens Sunday 09:00 + 60 min = Sunday 10:00
        $this->assertSame('2026-10-25 10:00:00', $dueLocal->format('Y-m-d H:i:s'));
    }

    public function test_ticket_created_before_opening_hours_starts_at_opening_same_day(): void
    {
        // Sunday 07:30:00
        $created = CarbonImmutable::parse('2026-10-25 07:30:00', 'Asia/Dhaka');

        $dueAt = $this->calculator->calculateDueAt($created, 120);
        $dueLocal = $dueAt->setTimezone('Asia/Dhaka');

        // Starts Sunday 09:00 + 120 min = Sunday 11:00
        $this->assertSame('2026-10-25 11:00:00', $dueLocal->format('Y-m-d H:i:s'));
    }

    public function test_multi_day_high_priority_ticket_calculation(): void
    {
        // Monday 10:00 AM (8 business hours = 480 min)
        $created = CarbonImmutable::parse('2026-10-26 10:00:00', 'Asia/Dhaka');

        $dueAt = $this->calculator->calculateDueAt($created, 480);
        $dueLocal = $dueAt->setTimezone('Asia/Dhaka');

        // 10:00 + 8 hours = 18:00 (closing time) on Monday
        $this->assertSame('2026-10-26 18:00:00', $dueLocal->format('Y-m-d H:i:s'));
    }

    public function test_warning_threshold_calculates_80_percent_of_business_window(): void
    {
        // Thursday 17:00: 120 minutes total -> warning is 80% = 96 minutes
        // Thursday: 60 min consumed (17:00 to 18:00) -> 36 min left
        // Sunday 09:00 + 36 min = Sunday 09:36
        $created = CarbonImmutable::parse('2026-10-22 17:00:00', 'Asia/Dhaka');

        $warningAt = $this->calculator->calculateWarningAt($created, 120, 80);
        $warningLocal = $warningAt->setTimezone('Asia/Dhaka');

        $this->assertSame('2026-10-25 09:36:00', $warningLocal->format('Y-m-d H:i:s'));
    }

    public function test_sla_status_resolver_states(): void
    {
        $due = Carbon::parse('2026-10-25 12:00:00');
        $warning = Carbon::parse('2026-10-25 10:00:00');

        $ticket = new Ticket([
            'status' => TicketStatus::Open,
            'sla_due_at' => $due,
            'sla_warning_at' => $warning,
        ]);

        // 1. Within SLA (before warning)
        $this->assertSame('within', $this->resolver->resolve($ticket, Carbon::parse('2026-10-25 09:00:00')));

        // 2. Approaching breach (between warning and due)
        $this->assertSame('approaching', $this->resolver->resolve($ticket, Carbon::parse('2026-10-25 10:30:00')));

        // 3. Breached (after due date)
        $this->assertSame('breached', $this->resolver->resolve($ticket, Carbon::parse('2026-10-25 13:00:00')));

        // 4. Met when resolved on time
        $ticket->status = TicketStatus::Resolved;
        $ticket->resolved_at = Carbon::parse('2026-10-25 11:00:00');
        $this->assertSame('met', $this->resolver->resolve($ticket));

        // 5. Breached when resolved late
        $ticket->resolved_at = Carbon::parse('2026-10-25 14:00:00');
        $this->assertSame('breached_resolved', $this->resolver->resolve($ticket));
    }
}
