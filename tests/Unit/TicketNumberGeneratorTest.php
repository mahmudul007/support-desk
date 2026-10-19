<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tickets\TicketNumberGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private TicketNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TicketNumberGenerator;
    }

    public function test_generates_sequential_ticket_numbers_for_current_year(): void
    {
        Carbon::setTestNow('2026-05-10 10:00:00');

        $first = $this->generator->next();
        $second = $this->generator->next();
        $third = $this->generator->next();

        $this->assertSame('TKT-2026-000001', $first);
        $this->assertSame('TKT-2026-000002', $second);
        $this->assertSame('TKT-2026-000003', $third);
    }

    public function test_resets_sequence_when_calendar_year_changes(): void
    {
        $year2026 = Carbon::parse('2026-12-31 23:59:00');
        $this->assertSame('TKT-2026-000001', $this->generator->next($year2026));
        $this->assertSame('TKT-2026-000002', $this->generator->next($year2026));

        $year2027 = Carbon::parse('2027-01-01 00:01:00');
        $this->assertSame('TKT-2027-000001', $this->generator->next($year2027));
        $this->assertSame('TKT-2027-000002', $this->generator->next($year2027));
    }

    public function test_reserve_range_allocates_contiguous_numbers_for_bulk_operations(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');

        // Pre-allocate 1 number
        $this->assertSame('TKT-2026-000001', $this->generator->next());

        // Batch reserve 4 numbers
        $range = $this->generator->reserveRange(4);

        $this->assertCount(4, $range);
        $this->assertSame([
            'TKT-2026-000002',
            'TKT-2026-000003',
            'TKT-2026-000004',
            'TKT-2026-000005',
        ], $range);

        // Next single call should be 6
        $this->assertSame('TKT-2026-000006', $this->generator->next());
    }

    public function test_reserve_range_with_zero_or_negative_returns_empty_array(): void
    {
        $this->assertSame([], $this->generator->reserveRange(0));
        $this->assertSame([], $this->generator->reserveRange(-5));
    }
}
