<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceRecapExporter
{
    /**
     * Build an XLSX workbook and return its binary contents.
     */
    public function build(): string
    {
        $spreadsheet = new Spreadsheet;

        $detailSheet = $spreadsheet->getActiveSheet();
        $detailSheet->setTitle('Rekap Absensi');
        $detailSheet->fromArray($this->detailRows(), null, 'A1');

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Total Kehadiran');
        $summarySheet->fromArray($this->summaryRows(), null, 'A1');

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'attendance-recap-');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $contents = file_get_contents($path);
        unlink($path);

        return $contents === false ? '' : $contents;
    }

    public function employeeSummaryQuery()
    {
        return User::query()
            ->where('role', UserRole::Employee->value)
            ->withCount([
                'attendances as on_time_count' => fn ($query) => $query->where('status', AttendanceStatus::OnTime->value),
                'attendances as late_count' => fn ($query) => $query->where('status', AttendanceStatus::Late->value),
                'attendances as sick_count' => fn ($query) => $query->where('status', AttendanceStatus::Sick->value),
                'attendances as leave_count' => fn ($query) => $query->where('status', AttendanceStatus::Leave->value),
                'attendances as permit_count' => fn ($query) => $query->where('status', AttendanceStatus::Permit->value),
                'attendances as absent_count' => fn ($query) => $query->where('status', AttendanceStatus::Absent->value),
            ])
            ->withMin('attendances as first_attendance_date', 'date')
            ->withMax('attendances as latest_attendance_date', 'date')
            ->orderBy('name');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function detailRows(): array
    {
        $rows = [[
            'Nama Karyawan',
            'Username',
            'Email',
            'Tanggal',
            'Status',
            'Kantor',
            'Waktu Masuk',
            'Waktu Keluar',
            'Durasi Jam',
            'Sumber',
            'Tipe Pengajuan',
            'Keterangan Pengajuan',
            'Catatan Review',
            'Reviewer',
            'Dibuat Oleh',
            'Diubah Oleh',
        ]];

        Attendance::with(['user', 'office', 'attendanceRequest.reviewer'])
            ->orderBy('date')
            ->orderBy('user_id')
            ->each(function (Attendance $attendance) use (&$rows): void {
                $request = $attendance->attendanceRequest;

                $rows[] = [
                    $attendance->user?->name,
                    $attendance->user?->username,
                    $attendance->user?->email,
                    $attendance->date?->format('Y-m-d'),
                    $this->statusLabel($attendance->status),
                    $attendance->office?->name,
                    $attendance->in_at?->format('Y-m-d H:i:s'),
                    $attendance->out_at?->format('Y-m-d H:i:s'),
                    $this->durationHours($attendance),
                    $this->sourceLabel($attendance),
                    $request ? $this->statusLabel($request->type) : null,
                    $request?->description,
                    $request?->rejection_reason,
                    $request?->reviewer?->username,
                    $attendance->created_by,
                    $attendance->updated_by,
                ];
            });

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function summaryRows(): array
    {
        $rows = [[
            'Nama Karyawan',
            'Username',
            'Email',
            'Tepat Waktu',
            'Terlambat',
            'Total Kehadiran',
            'Sakit',
            'Cuti',
            'Izin',
            'Tidak Hadir',
            'Tanggal Pertama',
            'Tanggal Terakhir',
        ]];

        $this->employeeSummaryQuery()
            ->each(function (User $employee) use (&$rows): void {
                $rows[] = [
                    $employee->name,
                    $employee->username,
                    $employee->email,
                    $employee->on_time_count,
                    $employee->late_count,
                    $employee->on_time_count + $employee->late_count,
                    $employee->sick_count,
                    $employee->leave_count,
                    $employee->permit_count,
                    $employee->absent_count,
                    $employee->first_attendance_date,
                    $employee->latest_attendance_date,
                ];
            });

        return $rows;
    }

    private function durationHours(Attendance $attendance): ?float
    {
        if (! $attendance->in_at || ! $attendance->out_at) {
            return null;
        }

        return round($attendance->in_at->diffInMinutes($attendance->out_at) / 60, 2);
    }

    private function sourceLabel(Attendance $attendance): string
    {
        if ($attendance->attendance_request_id !== null) {
            return 'Pengajuan';
        }

        if (in_array($attendance->status, [AttendanceStatus::Sick, AttendanceStatus::Leave, AttendanceStatus::Permit], true)) {
            return 'Input Manual';
        }

        return 'Absensi';
    }

    private function statusLabel(?AttendanceStatus $status): ?string
    {
        return match ($status) {
            AttendanceStatus::OnTime => 'Tepat Waktu',
            AttendanceStatus::Late => 'Terlambat',
            AttendanceStatus::Absent => 'Tidak Hadir',
            AttendanceStatus::Sick => 'Sakit',
            AttendanceStatus::Leave => 'Cuti',
            AttendanceStatus::Permit => 'Izin',
            null => null,
        };
    }
}
