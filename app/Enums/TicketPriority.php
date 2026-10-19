<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Default resolution window in business minutes.
     * Low: 48h (2880m), Medium: 24h (1440m), High: 8h (480m), Critical: 2h (120m).
     */
    public function defaultResolutionMinutes(): int
    {
        return match ($this) {
            self::Low => 2880,
            self::Medium => 1440,
            self::High => 480,
            self::Critical => 120,
        };
    }
}
