<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttendanceReportBuilder
{
    public function __construct(private readonly AttendanceAbsencePolicy $policy) {}

    /**
     * @return Collection<int, ProjectedAttendance>
     */
    public function detailRows(?AttendanceFilter $filter = null, ?User $actor = null, bool $descending = true, string $sortBy = 'created_at'): Collection
    {
        $filter ??= new AttendanceFilter;
        $query = Attendance::with(['user', 'office', 'attendanceRequest.user', 'attendanceRequest.reviewer']);
        $filter->apply($query, $actor);

        $rows = $query->get()
            ->map(fn (Attendance $attendance) => ProjectedAttendance::fromAttendance($attendance))
            ->toBase();

        $rows = $rows->merge($this->virtualRows($filter, $actor));

        return $this->sortDetailRows($rows, $descending, $sortBy)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summaryRows(?AttendanceFilter $filter = null, ?User $actor = null): Collection
    {
        $filter ??= new AttendanceFilter;
        $details = $this->detailRows($filter, $actor, false, 'date');
        $detailsByUser = $details->groupBy(fn (ProjectedAttendance $row) => $row->userId);
        $hasConstraints = $filter->hasConstraints();

        return $this->employeeQueryForFilter($filter, $actor)
            ->get()
            ->filter(fn (User $employee) => ! $hasConstraints || $detailsByUser->has($employee->id))
            ->map(function (User $employee) use ($detailsByUser): array {
                /** @var Collection<int, ProjectedAttendance> $rows */
                $rows = $detailsByUser->get($employee->id, collect());

                return [
                    'user_id' => $employee->id,
                    'name' => $employee->name,
                    'username' => $employee->username,
                    'email' => $employee->email,
                    'on_time_count' => $this->countStatus($rows, AttendanceStatus::OnTime),
                    'late_count' => $this->countStatus($rows, AttendanceStatus::Late),
                    'total_real_check_ins' => $this->countStatus($rows, AttendanceStatus::OnTime)
                        + $this->countStatus($rows, AttendanceStatus::Late),
                    'sick_count' => $this->countStatus($rows, AttendanceStatus::Sick),
                    'leave_count' => $this->countStatus($rows, AttendanceStatus::Leave),
                    'permit_count' => $this->countStatus($rows, AttendanceStatus::Permit),
                    'absent_count' => $this->countStatus($rows, AttendanceStatus::Absent),
                    'first_attendance_date' => $this->boundaryDate($rows, 'min'),
                    'latest_attendance_date' => $this->boundaryDate($rows, 'max'),
                ];
            })
            ->values();
    }

    /**
     * @return array{
     *     start_date: string,
     *     end_date: string,
     *     total: int,
     *     days: list<array<string, int|string>>
     * }
     */
    public function chartBuckets(?AttendanceFilter $filter = null, ?User $actor = null): array
    {
        $filter ??= new AttendanceFilter;
        $details = $this->detailRows($filter, $actor, false, 'date');
        [$startDate, $endDate] = $this->chartDateWindow($filter, $details);
        $statuses = collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $status) => $status->value);
        $days = [];
        $cursor = CarbonImmutable::parse($startDate);
        $lastDate = CarbonImmutable::parse($endDate);

        while ($cursor->lte($lastDate)) {
            $dateKey = $cursor->toDateString();
            $days[$dateKey] = ['date' => $dateKey, 'total' => 0];

            foreach ($statuses as $status) {
                $days[$dateKey][$status] = 0;
            }

            $cursor = $cursor->addDay();
        }

        $details->each(function (ProjectedAttendance $row) use (&$days): void {
            $dateKey = $row->date->toDateString();

            if (! isset($days[$dateKey])) {
                return;
            }

            $days[$dateKey][$row->status->value]++;
            $days[$dateKey]['total']++;
        });

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total' => $details->count(),
            'days' => array_values($days),
        ];
    }

    /**
     * @return Collection<int, ProjectedAttendance>
     */
    private function virtualRows(AttendanceFilter $filter, ?User $actor): Collection
    {
        $window = $this->dateWindow($filter);

        if ($window === null || $this->hasOfficeFilter($filter)) {
            return collect();
        }

        [$startDate, $endDate] = $window;
        $values = $filter->values();
        $statusFilter = isset($values['status']) && $values['status'] !== ''
            ? (string) $values['status']
            : null;

        $existingByUserDate = Attendance::query()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when(
                $this->filteredUserId($filter, $actor) !== null,
                fn (Builder $query) => $query->where('user_id', $this->filteredUserId($filter, $actor)),
            )
            ->get(['user_id', 'date'])
            ->mapWithKeys(fn (Attendance $attendance) => [
                $attendance->user_id.'-'.$attendance->date->format('Y-m-d') => true,
            ]);

        $rows = collect();

        $this->employeeQueryForFilter($filter, $actor)
            ->get()
            ->each(function (User $employee) use ($startDate, $endDate, $existingByUserDate, $statusFilter, $filter, $rows): void {
                $employeeStart = $this->policy->employeeStartDate($employee);
                $rangeStart = $this->policy->parseDate($startDate)->max($employeeStart);

                foreach ($this->policy->workdayDates($rangeStart, $endDate) as $date) {
                    $key = $employee->id.'-'.$date->toDateString();
                    if ($existingByUserDate->has($key)) {
                        continue;
                    }

                    $projection = $this->policy->nonRealStatusForDate($employee, $date);
                    $status = $projection['status'];
                    if ($statusFilter !== null && $status->value !== $statusFilter) {
                        continue;
                    }

                    $row = ProjectedAttendance::virtual($employee, $date, $status, $projection['request']);
                    if (! $this->matchesSearch($row, $filter)) {
                        continue;
                    }

                    $rows->push($row);
                }
            });

        return $rows;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function dateWindow(AttendanceFilter $filter): ?array
    {
        $values = $filter->values();

        if (! empty($values['date'])) {
            return [$values['date'], $values['date']];
        }

        if (! empty($values['start_date']) && ! empty($values['end_date'])) {
            return [$values['start_date'], $values['end_date']];
        }

        return null;
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $details
     * @return array{0: string, 1: string}
     */
    private function chartDateWindow(AttendanceFilter $filter, Collection $details): array
    {
        $window = $this->dateWindow($filter);

        if ($window !== null) {
            return $window;
        }

        if ($details->isNotEmpty()) {
            $dates = $details->map(fn (ProjectedAttendance $row) => $row->date->toDateString());

            return [$dates->min(), $dates->max()];
        }

        $endDate = CarbonImmutable::now();

        return [$endDate->subDays(6)->toDateString(), $endDate->toDateString()];
    }

    /**
     * @return Builder<User>
     */
    private function employeeQueryForFilter(AttendanceFilter $filter, ?User $actor): Builder
    {
        return $this->policy->eligibleEmployeesQuery($this->filteredUserId($filter, $actor));
    }

    private function filteredUserId(AttendanceFilter $filter, ?User $actor): ?int
    {
        if ($actor && ! $actor->isAdministrator()) {
            return $actor->id;
        }

        $values = $filter->values();

        return isset($values['user_id']) && $values['user_id'] !== ''
            ? (int) $values['user_id']
            : null;
    }

    private function hasOfficeFilter(AttendanceFilter $filter): bool
    {
        $values = $filter->values();

        return isset($values['office_id']) && $values['office_id'] !== '';
    }

    private function matchesSearch(ProjectedAttendance $row, AttendanceFilter $filter): bool
    {
        $values = $filter->values();
        if (empty($values['search'])) {
            return true;
        }

        $needle = Str::lower(trim((string) $values['search']));
        $statusLabels = [
            AttendanceStatus::Absent->value => 'absent tidak hadir',
            AttendanceStatus::Sick->value => 'sick sakit',
            AttendanceStatus::Leave->value => 'leave cuti',
            AttendanceStatus::Permit->value => 'permit izin',
        ];
        $haystack = Str::lower(implode(' ', [
            $row->date->format('Y-m-d'),
            $row->status->value,
            $statusLabels[$row->status->value] ?? '',
            $row->user?->name,
            $row->user?->username,
            $row->user?->email,
        ]));

        return Str::contains($haystack, $needle);
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $rows
     * @return Collection<int, ProjectedAttendance>
     */
    private function sortDetailRows(Collection $rows, bool $descending, string $sortBy): Collection
    {
        if ($sortBy === 'created_at') {
            return $this->sortDetailRowsByCreatedAt($rows, $descending);
        }

        return $this->sortDetailRowsByDate($rows, $descending);
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $rows
     * @return Collection<int, ProjectedAttendance>
     */
    private function sortDetailRowsByCreatedAt(Collection $rows, bool $descending): Collection
    {
        return $rows->sort(function (ProjectedAttendance $left, ProjectedAttendance $right) use ($descending): int {
            $leftCreatedAt = $this->createdAtTimestamp($left);
            $rightCreatedAt = $this->createdAtTimestamp($right);

            if ($leftCreatedAt === null && $rightCreatedAt !== null) {
                return 1;
            }

            if ($leftCreatedAt !== null && $rightCreatedAt === null) {
                return -1;
            }

            if ($leftCreatedAt !== null && $rightCreatedAt !== null && $leftCreatedAt !== $rightCreatedAt) {
                $createdAtComparison = $leftCreatedAt <=> $rightCreatedAt;

                return $descending ? -$createdAtComparison : $createdAtComparison;
            }

            return $this->compareDetailRowsByAttendanceDate($left, $right, $descending);
        });
    }

    private function createdAtTimestamp(ProjectedAttendance $row): ?int
    {
        if ($row->createdAt instanceof CarbonInterface) {
            return $row->createdAt->getTimestamp();
        }

        if ($row->createdAt === null || $row->createdAt === '') {
            return null;
        }

        return CarbonImmutable::parse($row->createdAt)->getTimestamp();
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $rows
     * @return Collection<int, ProjectedAttendance>
     */
    private function sortDetailRowsByDate(Collection $rows, bool $descending): Collection
    {
        return $rows->sort(function (ProjectedAttendance $left, ProjectedAttendance $right) use ($descending): int {
            return $this->compareDetailRowsByAttendanceDate($left, $right, $descending);
        });
    }

    private function compareDetailRowsByAttendanceDate(ProjectedAttendance $left, ProjectedAttendance $right, bool $descending): int
    {
        $dateComparison = strcmp($left->date->format('Y-m-d'), $right->date->format('Y-m-d'));
        if ($dateComparison !== 0) {
            return $descending ? -$dateComparison : $dateComparison;
        }

        $leftTime = $left->inAt?->format('H:i:s') ?? '';
        $rightTime = $right->inAt?->format('H:i:s') ?? '';
        $timeComparison = strcmp($leftTime, $rightTime);
        if ($timeComparison !== 0) {
            return $descending ? -$timeComparison : $timeComparison;
        }

        $userComparison = $left->userId <=> $right->userId;
        if ($userComparison !== 0) {
            return $userComparison;
        }

        return ($left->id ?? 0) <=> ($right->id ?? 0);
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $rows
     */
    private function countStatus(Collection $rows, AttendanceStatus $status): int
    {
        return $rows->filter(fn (ProjectedAttendance $row) => $row->status === $status)->count();
    }

    /**
     * @param  Collection<int, ProjectedAttendance>  $rows
     */
    private function boundaryDate(Collection $rows, string $mode): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $dates = $rows->map(fn (ProjectedAttendance $row) => $row->date->format('Y-m-d'));

        return $mode === 'min' ? $dates->min() : $dates->max();
    }
}
