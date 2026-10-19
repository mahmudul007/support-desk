<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TicketNumberGenerator
{
    /**
     * Allocate and return the next sequential ticket number for the specified year.
     *
     * Format: TKT-YYYY-000001
     */
    public function next(?CarbonInterface $now = null): string
    {
        $year = (int) ($now ?? now())->format('Y');

        return DB::transaction(function () use ($year): string {
            // Ensure sequence record exists for the year
            DB::table('ticket_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
            ]);

            // Acquire exclusive row lock to serialize number generation
            $sequence = DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = ((int) ($sequence->last_number ?? 0)) + 1;

            DB::table('ticket_sequences')
                ->where('year', $year)
                ->update(['last_number' => $nextNumber]);

            return $this->formatNumber($year, $nextNumber);
        });
    }

    /**
     * Reserve a contiguous range of sequential numbers for bulk imports.
     *
     * @return array<int, string> Array of formatted ticket numbers
     */
    public function reserveRange(int $count, ?CarbonInterface $now = null): array
    {
        if ($count <= 0) {
            return [];
        }

        $year = (int) ($now ?? now())->format('Y');

        return DB::transaction(function () use ($year, $count): array {
            DB::table('ticket_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
            ]);

            $sequence = DB::table('ticket_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $current = (int) ($sequence->last_number ?? 0);
            $newLast = $current + $count;

            DB::table('ticket_sequences')
                ->where('year', $year)
                ->update(['last_number' => $newLast]);

            $numbers = [];
            for ($i = 1; $i <= $count; $i++) {
                $numbers[] = $this->formatNumber($year, $current + $i);
            }

            return $numbers;
        });
    }

    /**
     * Format year and sequence number into standardized ticket number string.
     */
    protected function formatNumber(int $year, int $number): string
    {
        return sprintf('TKT-%d-%06d', $year, $number);
    }
}
