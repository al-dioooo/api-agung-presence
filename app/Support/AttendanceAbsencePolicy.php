<?php

namespace App\Support;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\AttendanceRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AttendanceAbsencePolicy
{
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * @return array<int, string>
     */
    public function realStatusValues(): array
    {
        return [
            AttendanceStatus::OnTime->value,
            AttendanceStatus::Late->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function nonRealStatusValues(): array
    {
        return [
            AttendanceStatus::Absent->value,
            AttendanceStatus::Sick->value,
            AttendanceStatus::Leave->value,
            AttendanceStatus::Permit->value,
        ];
    }

    public function isRealStatus(AttendanceStatus|string $status): bool
    {
        $value = $status instanceof AttendanceStatus ? $status->value : $status;

        return in_array($value, $this->realStatusValues(), true);
    }

    public function isNonRealStatus(AttendanceStatus|string $status): bool
    {
        $value = $status instanceof AttendanceStatus ? $status->value : $status;

        return in_array($value, $this->nonRealStatusValues(), true);
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now(self::TIMEZONE))->startOfDay();
    }

    public function parseDate(string|CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
    }

    public function employeeStartDate(User $employee): CarbonImmutable
    {
        return CarbonImmutable::parse($employee->created_at, self::TIMEZONE)->startOfDay();
    }

    /**
     * @return Builder<User>
     */
    public function eligibleEmployeesQuery(?int $userId = null): Builder
    {
        return User::query()
            ->where('role', UserRole::Employee->value)
            ->when($userId !== null, fn (Builder $query) => $query->whereKey($userId))
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    public function workdayDates(string|CarbonInterface $startDate, string|CarbonInterface $endDate, bool $clampToToday = true): array
    {
        $start = $this->parseDate($startDate);
        $end = $this->parseDate($endDate);

        if ($clampToToday && $end->gt($this->today())) {
            $end = $this->today();
        }

        if ($start->gt($end)) {
            return [];
        }

        return AttendanceWorkdays::dates($start, $end);
    }

    public function approvedRequestForDate(int $userId, CarbonInterface $date): ?AttendanceRequest
    {
        $dateKey = $date->format('Y-m-d');

        return AttendanceRequest::query()
            ->where('user_id', $userId)
            ->where('approval_status', AttendanceRequestStatus::Approved->value)
            ->whereDate('start_date', '<=', $dateKey)
            ->whereDate('end_date', '>=', $dateKey)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{status: AttendanceStatus, request: AttendanceRequest|null}
     */
    public function nonRealStatusForDate(User $employee, CarbonInterface $date): array
    {
        $request = $this->approvedRequestForDate($employee->id, $date);

        return [
            'status' => $request?->type ?? AttendanceStatus::Absent,
            'request' => $request,
        ];
    }
}
