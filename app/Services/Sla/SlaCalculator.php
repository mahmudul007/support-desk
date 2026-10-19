<?php

declare(strict_types=1);

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SlaCalculator
{
    public function __construct(
        protected BusinessHours $businessHours = new BusinessHours
    ) {}

    /**
     * Calculate resolution deadline based on configured business hours.
     * Returned timestamp is standardized in UTC.
     */
    public function calculateDueAt(CarbonInterface $from, int $businessMinutes): CarbonImmutable
    {
        if ($businessMinutes <= 0) {
            return CarbonImmutable::instance($from)->setTimezone('UTC');
        }

        $cursor = $this->businessHours->nextBusinessMoment($from);
        $remaining = $businessMinutes;

        while ($remaining > 0) {
            $cursor = $this->businessHours->nextBusinessMoment($cursor);
            $availableToday = $this->businessHours->minutesUntilEndOfBusinessDay($cursor);

            if ($remaining <= $availableToday) {
                return $cursor->addMinutes($remaining)->setTimezone('UTC');
            }

            $remaining -= $availableToday;
            // Advance to the start of the next day
            $cursor = $cursor->addDay()->startOfDay();
        }

        return $cursor->setTimezone('UTC');
    }

    /**
     * Calculate SLA warning threshold timestamp (defaults to 80% of business duration).
     * Returned timestamp is standardized in UTC.
     */
    public function calculateWarningAt(
        CarbonInterface $from,
        int $businessMinutes,
        int $thresholdPercent = 80
    ): CarbonImmutable {
        $warningMinutes = (int) round($businessMinutes * ($thresholdPercent / 100));

        return $this->calculateDueAt($from, $warningMinutes);
    }

    /**
     * Compute both due and warning timestamps for a specific ticket priority.
     *
     * @return array{sla_due_at: CarbonImmutable, sla_warning_at: CarbonImmutable}
     */
    public function calculateTimestampsForPriority(
        TicketPriority $priority,
        CarbonInterface $from
    ): array {
        // Query database SLA policy if exists, otherwise fallback to enum defaults
        $policy = SlaPolicy::query()->where('priority', $priority->value)->first();

        $minutes = $policy ? $policy->resolution_minutes : $priority->defaultResolutionMinutes();
        $warningPercent = $policy ? $policy->warning_threshold_percent : (int) config('sla.default_warning_percent', 80);

        return [
            'sla_due_at' => $this->calculateDueAt($from, $minutes),
            'sla_warning_at' => $this->calculateWarningAt($from, $minutes, $warningPercent),
        ];
    }
}
