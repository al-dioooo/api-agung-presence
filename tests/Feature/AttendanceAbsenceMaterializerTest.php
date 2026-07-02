<?php

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('attendance_absence_materialization creates one absent row per missing employee workday', function () {
    $missingEmployee = User::factory()->employee()->create([
        'created_at' => '2026-06-01 09:00:00',
    ]);
    $presentEmployee = User::factory()->employee()->create([
        'created_at' => '2026-06-01 09:00:00',
    ]);
    User::factory()->administrator()->create([
        'created_at' => '2026-06-01 09:00:00',
    ]);
    $deletedEmployee = User::factory()->employee()->create([
        'created_at' => '2026-06-01 09:00:00',
    ]);
    $deletedEmployee->delete();
    $office = Office::factory()->create();

    Attendance::factory()->create([
        'user_id' => $presentEmployee->id,
        'office_id' => $office->id,
        'date' => '2026-06-06',
        'status' => AttendanceStatus::OnTime,
    ]);

    $this->artisan('attendances:materialize-absences', [
        '--date' => '2026-06-06',
    ])
        ->expectsOutput('Created: 1; Updated: 0; Skipped: 1')
        ->assertSuccessful();

    expect(Attendance::query()
        ->where('user_id', $missingEmployee->id)
        ->whereDate('date', '2026-06-06')
        ->whereNull('office_id')
        ->whereNull('in_at')
        ->whereNull('in_latitude')
        ->whereNull('in_longitude')
        ->where('status', AttendanceStatus::Absent->value)
        ->where('created_by', 'system')
        ->exists())->toBeTrue();

    expect(Attendance::query()
        ->whereDate('date', '2026-06-06')
        ->where('user_id', $missingEmployee->id)
        ->count())->toBe(1);

    $this->artisan('attendances:materialize-absences', [
        '--date' => '2026-06-06',
    ])
        ->expectsOutput('Created: 0; Updated: 0; Skipped: 2')
        ->assertSuccessful();

    expect(Attendance::query()
        ->whereDate('date', '2026-06-06')
        ->whereIn('user_id', [$missingEmployee->id, $presentEmployee->id])
        ->count())->toBe(2);
});

test('attendance_absence_backfill starts from employee creation date and skips Sundays and future dates', function () {
    $firstEmployee = User::factory()->employee()->create([
        'created_at' => '2026-06-05 15:30:00',
    ]);
    $secondEmployee = User::factory()->employee()->create([
        'created_at' => '2026-06-08 08:00:00',
    ]);
    User::factory()->administrator()->create([
        'created_at' => '2026-06-01 08:00:00',
    ]);

    $this->artisan('attendances:materialize-absences', [
        '--start-date' => '2026-06-01',
        '--end-date' => '2026-06-12',
    ])
        ->expectsOutput('Created: 8; Updated: 0; Skipped: 0')
        ->assertSuccessful();

    expect(Attendance::query()
        ->where('user_id', $firstEmployee->id)
        ->orderBy('date')
        ->get()
        ->map(fn (Attendance $attendance) => $attendance->date->format('Y-m-d'))
        ->all())->toBe([
            '2026-06-05',
            '2026-06-06',
            '2026-06-08',
            '2026-06-09',
            '2026-06-10',
        ])
        ->and(Attendance::query()
            ->where('user_id', $secondEmployee->id)
            ->orderBy('date')
            ->get()
            ->map(fn (Attendance $attendance) => $attendance->date->format('Y-m-d'))
            ->all())->toBe([
                '2026-06-08',
                '2026-06-09',
                '2026-06-10',
            ]);

    $this->assertDatabaseMissing('attendances', [
        'user_id' => $firstEmployee->id,
        'date' => '2026-06-07',
    ]);
    $this->assertDatabaseMissing('attendances', [
        'user_id' => $firstEmployee->id,
        'date' => '2026-06-11',
    ]);
});

test('materializer uses approved request status for arrived workdays only', function () {
    $admin = User::factory()->administrator()->create();
    $employee = User::factory()->employee()->create([
        'created_at' => '2026-06-01 08:00:00',
    ]);
    $attendanceRequest = AttendanceRequest::factory()->create([
        'user_id' => $employee->id,
        'type' => AttendanceStatus::Leave,
        'start_date' => '2026-06-09',
        'end_date' => '2026-06-12',
        'approval_status' => AttendanceRequestStatus::Approved,
        'reviewed_by' => $admin->id,
        'reviewed_at' => '2026-06-09 09:00:00',
        'proof_photo' => 'data:image/png;base64,'.base64_encode('proof'),
    ]);

    $this->artisan('attendances:materialize-absences', [
        '--start-date' => '2026-06-09',
        '--end-date' => '2026-06-12',
    ])
        ->expectsOutput('Created: 2; Updated: 0; Skipped: 0')
        ->assertSuccessful();

    foreach (['2026-06-09', '2026-06-10'] as $date) {
        expect(Attendance::query()
            ->where('user_id', $employee->id)
            ->where('attendance_request_id', $attendanceRequest->id)
            ->whereDate('date', $date)
            ->where('status', AttendanceStatus::Leave->value)
            ->exists())->toBeTrue();
    }

    expect(Attendance::query()
        ->where('user_id', $employee->id)
        ->whereDate('date', '2026-06-11')
        ->exists())->toBeFalse();
});
