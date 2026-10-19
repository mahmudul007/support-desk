<?php

declare(strict_types=1);

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class BusinessHours
{
    protected string $timezone;

    /**
     * @var array<int, int>
     */
    protected array $businessDays;

    protected string $startTime;

    protected string $endTime;

    public function __construct(
        ?string $timezone = null,
        ?array $businessDays = null,
        ?string $startTime = null,
        ?string $endTime = null,
    ) {
        $this->timezone = $timezone ?? config('sla.timezone', 'Asia/Dhaka');
        $this->businessDays = $businessDays ?? config('sla.business_days', [0, 1, 2, 3, 4]);
        $this->startTime = $startTime ?? config('sla.business_start', '09:00');
        $this->endTime = $endTime ?? config('sla.business_end', '18:00');
    }

    /**
     * Get timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * Check if a given day is a configured business day.
     */
    public function isBusinessDay(CarbonInterface $date): bool
    {
        $local = CarbonImmutable::instance($date)->setTimezone($this->timezone);

        return in_array($local->dayOfWeek, $this->businessDays, true);
    }

    /**
     * Check if a specific moment is within business working hours.
     */
    public function isBusinessMoment(CarbonInterface $moment): bool
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($this->timezone);

        if (! $this->isBusinessDay($local)) {
            return false;
        }

        $start = $this->getBusinessStartTime($local);
        $end = $this->getBusinessEndTime($local);

        return $local->greaterThanOrEqualTo($start) && $local->lessThan($end);
    }

    /**
     * Find the next business moment.
     * If currently within business hours, returns the moment itself.
     * Otherwise, advances to the opening of the next business day.
     */
    public function nextBusinessMoment(CarbonInterface $moment): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($moment)->setTimezone($this->timezone);

        while (true) {
            if ($this->isBusinessDay($cursor)) {
                $start = $this->getBusinessStartTime($cursor);
                $end = $this->getBusinessEndTime($cursor);

                if ($cursor->lessThan($start)) {
                    return $start;
                }

                if ($cursor->lessThan($end)) {
                    return $cursor;
                }
            }

            // Move to start of next calendar day
            $cursor = $cursor->addDay()->startOfDay();
        }
    }

    /**
     * Calculate remaining business minutes in the current day from the given moment.
     */
    public function minutesUntilEndOfBusinessDay(CarbonInterface $moment): int
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($this->timezone);
        $end = $this->getBusinessEndTime($local);

        if ($local->greaterThanOrEqualTo($end)) {
            return 0;
        }

        return (int) $local->diffInMinutes($end);
    }

    /**
     * Get opening time on a specified date.
     */
    public function getBusinessStartTime(CarbonInterface $date): CarbonImmutable
    {
        $local = CarbonImmutable::instance($date)->setTimezone($this->timezone);
        [$hour, $minute] = explode(':', $this->startTime);

        return $local->setTime((int) $hour, (int) $minute, 0);
    }

    /**
     * Get closing time on a specified date.
     */
    public function getBusinessEndTime(CarbonInterface $date): CarbonImmutable
    {
        $local = CarbonImmutable::instance($date)->setTimezone($this->timezone);
        [$hour, $minute] = explode(':', $this->endTime);

        return $local->setTime((int) $hour, (int) $minute, 0);
    }
}
