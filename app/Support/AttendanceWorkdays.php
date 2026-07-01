<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class AttendanceWorkdays
{
    /**
     * Count inclusive Monday-Saturday workdays in a date range.
     */
    public static function count(string|DateTimeInterface|null $startDate, string|DateTimeInterface|null $endDate): int
    {
        return count(self::dates($startDate, $endDate));
    }

    /**
     * Return inclusive Monday-Saturday dates in a date range.
     *
     * @return array<int, CarbonImmutable>
     */
    public static function dates(string|DateTimeInterface|null $startDate, string|DateTimeInterface|null $endDate): array
    {
        if (! $startDate || ! $endDate) {
            return [];
        }

        $cursor = self::toDate($startDate);
        $end = self::toDate($endDate);

        if ($cursor->gt($end)) {
            return [];
        }

        $dates = [];

        while ($cursor->lte($end)) {
            if ($cursor->dayOfWeekIso <= 6) {
                $dates[] = $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    private static function toDate(string|DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfDay();
    }
}
