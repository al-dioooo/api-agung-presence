<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case Absent = 'absent';
    case Sick = 'sick';
    case Leave = 'leave';
    case Permit = 'permit';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::OnTime => 'On Time',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Sick => 'Sick',
            self::Leave => 'Leave',
            self::Permit => 'Permit',
        };
    }
}
