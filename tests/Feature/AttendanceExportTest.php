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

function proofPhotoDataUrl(): string
{
    $image = imagecreatetruecolor(4, 4);
    $color = imagecolorallocate($image, 34, 139, 230);
    imagefill($image, 0, 0, $color);

    ob_start();
    imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,'.base64_encode($contents === false ? '' : $contents);
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
            'proof_photo' => proofPhotoDataUrl(),
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
            'proof_photo' => 'invalid-photo-data',
            'status' => AttendanceStatus::Permit,
            'created_by' => $admin->username,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('attendances.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');

        $spreadsheet = workbookFromResponseContent($response->getContent());
        expect($spreadsheet->getSheetNames())->toBe(['Rekap Absensi', 'Total Kehadiran']);

        $detail = $spreadsheet->getSheetByName('Rekap Absensi');
        $summary = $spreadsheet->getSheetByName('Total Kehadiran');

        expect($detail->rangeToArray('A1:R1')[0])->toBe([
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
            'Ada Foto',
            'Foto Bukti',
            'Tipe Pengajuan',
            'Keterangan Pengajuan',
            'Catatan Review',
            'Reviewer',
            'Dibuat Oleh',
            'Diubah Oleh',
        ]);
        expect($detail->getAutoFilter()->getRange())->toBe('A1:R3')
            ->and($detail->getFreezePane())->toBe('A2')
            ->and($detail->getCell('K2')->getValue())->toBe('Ya')
            ->and($detail->getCell('K3')->getValue())->toBe('Tidak')
            ->and(count($detail->getDrawingCollection()))->toBe(1);

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
        expect($summary->getAutoFilter()->getRange())->toBe('A1:L2')
            ->and($summary->getFreezePane())->toBe('A2');

        expect($summary->getCell('F2')->getValue())->toBe(1)
            ->and($summary->getCell('I2')->getValue())->toBe(1);
    });

    test('export applies current attendance filters', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Nadia Putri',
            'username' => 'nadia',
            'email' => 'nadia@example.com',
        ]);
        $otherEmployee = User::factory()->employee()->create();
        $office = Office::factory()->create(['name' => 'Main Office']);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Late,
            'proof_photo' => proofPhotoDataUrl(),
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::OnTime,
        ]);
        Attendance::factory()->create([
            'user_id' => $otherEmployee->id,
            'office_id' => $office->id,
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Late,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('attendances.export', [
                'search' => 'nadia',
                'user_id' => $employee->id,
                'status' => AttendanceStatus::Late->value,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ]));

        $response->assertOk();

        $spreadsheet = workbookFromResponseContent($response->getContent());
        $detail = $spreadsheet->getSheetByName('Rekap Absensi');
        $summary = $spreadsheet->getSheetByName('Total Kehadiran');

        expect($detail->getHighestRow())->toBe(2)
            ->and($detail->getCell('A2')->getValue())->toBe('Nadia Putri')
            ->and($detail->getCell('E2')->getValue())->toBe('Terlambat')
            ->and($detail->getCell('K2')->getValue())->toBe('Ya')
            ->and(count($detail->getDrawingCollection()))->toBe(1)
            ->and($summary->getHighestRow())->toBe(2)
            ->and($summary->getCell('A2')->getValue())->toBe('Nadia Putri')
            ->and($summary->getCell('D2')->getValue())->toBe(0)
            ->and($summary->getCell('E2')->getValue())->toBe(1)
            ->and($summary->getCell('F2')->getValue())->toBe(1);
    });

    test('export handles roughly five hundred filtered rows', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();

        Attendance::factory()->count(500)->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'status' => AttendanceStatus::OnTime,
            'proof_photo' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('attendances.export', ['user_id' => $employee->id]));

        $response->assertOk();

        $spreadsheet = workbookFromResponseContent($response->getContent());
        $detail = $spreadsheet->getSheetByName('Rekap Absensi');

        expect($detail->getHighestRow())->toBe(501)
            ->and($detail->getAutoFilter()->getRange())->toBe('A1:R501');
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
