<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceAbsenceMaterializer
{
    public function __construct(private readonly AttendanceAbsencePolicy $policy) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function materialize(
        ?string $date = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $userId = null,
        string $username = 'system',
    ): array {
        $start = $date ?? $startDate ?? $this->policy->today()->toDateString();
        $end = $date ?? $endDate ?? $start;
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        DB::transaction(function () use ($start, $end, $userId, $username, &$result): void {
            $this->policy->eligibleEmployeesQuery($userId)
                ->each(function (User $employee) use ($start, $end, $username, &$result): void {
                    $employeeStart = $this->policy->employeeStartDate($employee);
                    $rangeStart = $this->policy->parseDate($start)->max($employeeStart);

                    foreach ($this->policy->workdayDates($rangeStart, $end) as $workday) {
                        $this->materializeForEmployeeDate($employee, $workday, $username, $result);
                    }
                });
        });

        return $result;
    }

    /**
     * @param  array{created: int, updated: int, skipped: int}  $result
     */
    private function materializeForEmployeeDate(User $employee, CarbonImmutable $date, string $username, array &$result): void
    {
        $attendance = Attendance::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->orderBy('id')
            ->first();

        if ($attendance && $this->policy->isRealStatus($attendance->status)) {
            $result['skipped']++;

            return;
        }

        $projection = $this->policy->nonRealStatusForDate($employee, $date);
        $status = $projection['status'];
        $request = $projection['request'];

        if ($attendance && $attendance->status !== AttendanceStatus::Absent && $request === null) {
            $result['skipped']++;

            return;
        }

        $data = [
            'attendance_request_id' => $request?->id,
            'office_id' => null,
            'date' => $date->toDateString(),
            'in_at' => null,
            'out_at' => null,
            'in_latitude' => null,
            'in_longitude' => null,
            'proof_photo' => $request?->proof_photo,
            'status' => $status,
            'updated_by' => $username,
        ];

        if ($attendance) {
            if (
                $attendance->attendance_request_id === $request?->id
                && $attendance->status === $status
                && $attendance->office_id === null
                && $attendance->in_at === null
                && $attendance->out_at === null
                && $attendance->in_latitude === null
                && $attendance->in_longitude === null
                && $attendance->proof_photo === $request?->proof_photo
            ) {
                $result['skipped']++;

                return;
            }

            $attendance->update($data);
            $result['updated']++;

            return;
        }

        Attendance::create([
            ...$data,
            'user_id' => $employee->id,
            'created_by' => $username,
        ]);
        $result['created']++;
    }
}
