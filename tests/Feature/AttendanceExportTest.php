<?php

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(LazilyRefreshDatabase::class);

function workbookFromResponseContent(string $content)
{
    $path = tempnam(sys_get_temp_dir(), 'attendance-export-test-');
    file_put_contents($path, $content);
    $spreadsheet = IOFactory::load($path);
    unlink($path);

    return $spreadsheet;
}

describe('export', function () {
    test('administrator can download a complete xlsx recap with detail and summary sheets', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Citra Dewi',
            'username' => 'citra',
            'email' => 'citra@example.com',
        ]);
        $office = Office::factory()->create(['name' => 'Main Office']);
        $attendanceRequest = AttendanceRequest::factory()->approved($admin)->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Permit,
            'description' => 'Mengurus dokumen.',
            'approval_status' => AttendanceRequestStatus::Approved,
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-06-01',
            'in_at' => '2026-06-01 08:00:00',
            'out_at' => '2026-06-01 17:00:00',
            'status' => AttendanceStatus::OnTime,
            'created_by' => 'system',
        ]);
        Attendance::factory()->create([
            'attendance_request_id' => $attendanceRequest->id,
            'user_id' => $employee->id,
            'office_id' => null,
            'date' => '2026-06-02',
            'in_at' => null,
            'out_at' => null,
            'in_latitude' => null,
            'in_longitude' => null,
            'status' => AttendanceStatus::Permit,
            'created_by' => $admin->username,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('attendances.export', ['date' => '2026-06-01']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');

        $spreadsheet = workbookFromResponseContent($response->getContent());
        expect($spreadsheet->getSheetNames())->toBe(['Rekap Absensi', 'Total Kehadiran']);

        $detail = $spreadsheet->getSheetByName('Rekap Absensi');
        $summary = $spreadsheet->getSheetByName('Total Kehadiran');

        expect($detail->rangeToArray('A1:P1')[0])->toBe([
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
        ]);

        expect($summary->rangeToArray('A1:L1')[0])->toBe([
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
        ]);

        expect($summary->getCell('F2')->getValue())->toBe(1)
            ->and($summary->getCell('I2')->getValue())->toBe(1);
    });

    test('export is administrator only', function () {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('attendances.export'))
            ->assertForbidden();

        $this->getJson(route('attendances.export'))
            ->assertForbidden();
    });
});
