<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sla\BusinessHours;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    protected BusinessHours $businessHours;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard Asia/Dhaka schedule: Sun(0) to Thu(4), 09:00 to 18:00
        $this->businessHours = new BusinessHours(
            timezone: 'Asia/Dhaka',
            businessDays: [0, 1, 2, 3, 4],
            startTime: '09:00',
            endTime: '18:00'
        );
    }

    public function test_is_business_day_identifies_workdays_and_weekends(): void
    {
        // 2026-10-18 is Sunday (Day 0) -> business day
        $sunday = CarbonImmutable::parse('2026-10-18 10:00:00', 'Asia/Dhaka');
        $this->assertTrue($this->businessHours->isBusinessDay($sunday));

        // 2026-10-22 is Thursday (Day 4) -> business day
        $thursday = CarbonImmutable::parse('2026-10-22 10:00:00', 'Asia/Dhaka');
        $this->assertTrue($this->businessHours->isBusinessDay($thursday));

        // 2026-10-23 is Friday (Day 5) -> weekend
        $friday = CarbonImmutable::parse('2026-10-23 10:00:00', 'Asia/Dhaka');
        $this->assertFalse($this->businessHours->isBusinessDay($friday));

        // 2026-10-24 is Saturday (Day 6) -> weekend
        $saturday = CarbonImmutable::parse('2026-10-24 10:00:00', 'Asia/Dhaka');
        $this->assertFalse($this->businessHours->isBusinessDay($saturday));
    }

    public function test_is_business_moment_evaluates_exact_working_hours(): void
    {
        // Sunday at 08:59:59 (before open) -> false
        $beforeOpen = CarbonImmutable::parse('2026-10-18 08:59:59', 'Asia/Dhaka');
        $this->assertFalse($this->businessHours->isBusinessMoment($beforeOpen));

        // Sunday at 09:00:00 (exact open) -> true
        $open = CarbonImmutable::parse('2026-10-18 09:00:00', 'Asia/Dhaka');
        $this->assertTrue($this->businessHours->isBusinessMoment($open));

        // Sunday at 14:30:00 (midday) -> true
        $midday = CarbonImmutable::parse('2026-10-18 14:30:00', 'Asia/Dhaka');
        $this->assertTrue($this->businessHours->isBusinessMoment($midday));

        // Sunday at 17:59:59 (just before close) -> true
        $beforeClose = CarbonImmutable::parse('2026-10-18 17:59:59', 'Asia/Dhaka');
        $this->assertTrue($this->businessHours->isBusinessMoment($beforeClose));

        // Sunday at 18:00:00 (exact close) -> false
        $close = CarbonImmutable::parse('2026-10-18 18:00:00', 'Asia/Dhaka');
        $this->assertFalse($this->businessHours->isBusinessMoment($close));

        // Friday at 12:00:00 (weekend during work hours) -> false
        $weekendMidday = CarbonImmutable::parse('2026-10-23 12:00:00', 'Asia/Dhaka');
        $this->assertFalse($this->businessHours->isBusinessMoment($weekendMidday));
    }

    public function test_next_business_moment_advances_correctly(): void
    {
        // Currently in business hours -> returns current moment
        $inHours = CarbonImmutable::parse('2026-10-18 11:30:00', 'Asia/Dhaka');
        $this->assertSame('2026-10-18 11:30:00', $this->businessHours->nextBusinessMoment($inHours)->format('Y-m-d H:i:s'));

        // Sunday before open (07:00) -> returns Sunday 09:00
        $beforeOpen = CarbonImmutable::parse('2026-10-18 07:00:00', 'Asia/Dhaka');
        $this->assertSame('2026-10-18 09:00:00', $this->businessHours->nextBusinessMoment($beforeOpen)->format('Y-m-d H:i:s'));

        // Sunday after close (19:30) -> returns Monday 09:00
        $afterClose = CarbonImmutable::parse('2026-10-18 19:30:00', 'Asia/Dhaka');
        $this->assertSame('2026-10-19 09:00:00', $this->businessHours->nextBusinessMoment($afterClose)->format('Y-m-d H:i:s'));

        // Thursday after close (20:00) -> skips Friday & Saturday, returns Sunday 09:00
        $thursdayNight = CarbonImmutable::parse('2026-10-22 20:00:00', 'Asia/Dhaka');
        $this->assertSame('2026-10-25 09:00:00', $this->businessHours->nextBusinessMoment($thursdayNight)->format('Y-m-d H:i:s'));

        // Friday afternoon -> returns Sunday 09:00
        $fridayAfternoon = CarbonImmutable::parse('2026-10-23 15:00:00', 'Asia/Dhaka');
        $this->assertSame('2026-10-25 09:00:00', $this->businessHours->nextBusinessMoment($fridayAfternoon)->format('Y-m-d H:i:s'));
    }

    public function test_minutes_until_end_of_business_day(): void
    {
        // Sunday 09:00 (full day) -> 9 hours = 540 minutes
        $startOfDay = CarbonImmutable::parse('2026-10-18 09:00:00', 'Asia/Dhaka');
        $this->assertSame(540, $this->businessHours->minutesUntilEndOfBusinessDay($startOfDay));

        // Sunday 17:00 -> 1 hour = 60 minutes
        $nearEnd = CarbonImmutable::parse('2026-10-18 17:00:00', 'Asia/Dhaka');
        $this->assertSame(60, $this->businessHours->minutesUntilEndOfBusinessDay($nearEnd));

        // Sunday 18:00 (past end) -> 0 minutes
        $pastEnd = CarbonImmutable::parse('2026-10-18 18:00:00', 'Asia/Dhaka');
        $this->assertSame(0, $this->businessHours->minutesUntilEndOfBusinessDay($pastEnd));
    }
}
