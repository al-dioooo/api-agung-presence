<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AttendanceRecapExporter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AttendanceReportController extends Controller
{
    use ApiResponse;

    public function summary(AttendanceRecapExporter $exporter): JsonResponse
    {
        $summary = $exporter->employeeSummaryQuery()
            ->get()
            ->map(fn ($employee) => [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'username' => $employee->username,
                'email' => $employee->email,
                'on_time_count' => $employee->on_time_count,
                'late_count' => $employee->late_count,
                'total_real_check_ins' => $employee->on_time_count + $employee->late_count,
                'sick_count' => $employee->sick_count,
                'leave_count' => $employee->leave_count,
                'permit_count' => $employee->permit_count,
                'absent_count' => $employee->absent_count,
                'first_attendance_date' => $employee->first_attendance_date,
                'latest_attendance_date' => $employee->latest_attendance_date,
            ]);

        return $this->success('Attendance summary retrieved successfully.', $summary);
    }

    public function export(AttendanceRecapExporter $exporter): Response
    {
        $filename = 'attendance-recap-'.now()->format('Ymd-His').'.xlsx';

        return response($exporter->build(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
