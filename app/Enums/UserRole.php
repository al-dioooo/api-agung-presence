<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Employee = 'employee';

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Employee => 'Employee',
        };
    }
}
