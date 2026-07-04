<?php

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('summary', function () {
    test('administrator can retrieve per employee real check in totals', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Budi Santoso',
            'username' => 'budi',
            'email' => 'budi@example.com',
        ]);

        foreach ([
            AttendanceStatus::OnTime,
            AttendanceStatus::Late,
            AttendanceStatus::Sick,
            AttendanceStatus::Leave,
            AttendanceStatus::Permit,
            AttendanceStatus::Absent,
        ] as $index => $status) {
            Attendance::factory()->create([
                'user_id' => $employee->id,
                'date' => '2026-06-0'.($index + 1),
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.summary'));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance summary retrieved successfully.')
            ->assertJsonPath('data.0.user_id', $employee->id)
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.0.username', 'budi')
            ->assertJsonPath('data.0.email', 'budi@example.com')
            ->assertJsonPath('data.0.on_time_count', 1)
            ->assertJsonPath('data.0.late_count', 1)
            ->assertJsonPath('data.0.total_real_check_ins', 2)
            ->assertJsonPath('data.0.sick_count', 1)
            ->assertJsonPath('data.0.leave_count', 1)
            ->assertJsonPath('data.0.permit_count', 1)
            ->assertJsonPath('data.0.absent_count', 1);
    });

    test('attendance summary is paginated by default', function () {
        $admin = User::factory()->administrator()->create();
        User::factory()->employee()->count(20)->sequence(
            fn ($sequence) => ['name' => sprintf('Employee %02d', $sequence->index + 1)],
        )->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.summary', ['page' => 2]));

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.from', 16)
            ->assertJsonPath('meta.to', 20);
    });

    test('attendance chart returns complete unpaginated status buckets', function () {
        $employees = User::factory()->employee()->count(20)->create([
            'created_at' => '2026-06-01 08:00:00',
        ]);
        $office = Office::factory()->create();

        $employees->each(function (User $employee) use ($office): void {
            Attendance::factory()->create([
                'user_id' => $employee->id,
                'office_id' => $office->id,
                'date' => '2026-06-10',
                'status' => AttendanceStatus::OnTime,
            ]);
        });
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.chart', [
                'date' => '2026-06-10',
                'per_page' => 1,
            ]));

        $response->assertOk()
            ->assertJsonMissingPath('meta')
            ->assertJsonPath('message', 'Attendance chart retrieved successfully.')
            ->assertJsonPath('data.start_date', '2026-06-10')
            ->assertJsonPath('data.end_date', '2026-06-10')
            ->assertJsonPath('data.total', 20)
            ->assertJsonPath('data.days.0.date', '2026-06-10')
            ->assertJsonPath('data.days.0.on_time', 20);
    });

    test('summary applies attendance filters before counting', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Dina Marlina',
            'username' => 'dina',
            'email' => 'dina@example.com',
        ]);
        $otherEmployee = User::factory()->employee()->create([
            'name' => 'Rafi Pratama',
        ]);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Late,
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::OnTime,
        ]);
        Attendance::factory()->create([
            'user_id' => $otherEmployee->id,
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Late,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.summary', [
                'search' => 'dina',
                'user_id' => $employee->id,
                'status' => AttendanceStatus::Late->value,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $employee->id)
            ->assertJsonPath('data.0.on_time_count', 0)
            ->assertJsonPath('data.0.late_count', 1)
            ->assertJsonPath('data.0.total_real_check_ins', 1)
            ->assertJsonPath('data.0.first_attendance_date', '2026-06-10')
            ->assertJsonPath('data.0.latest_attendance_date', '2026-06-10');
    });

    test('summary counts multi day approved requests by Monday through Saturday workdays', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create([
            'name' => 'Fitri Lestari',
            'username' => 'fitri',
            'email' => 'fitri@example.com',
        ]);
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Permit,
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-08',
        ]);

        $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ])
            ->assertOk();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.summary', [
                'user_id' => $employee->id,
                'status' => AttendanceStatus::Permit->value,
                'start_date' => '2026-06-05',
                'end_date' => '2026-06-08',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $employee->id)
            ->assertJsonPath('data.0.permit_count', 3)
            ->assertJsonPath('data.0.first_attendance_date', '2026-06-05')
            ->assertJsonPath('data.0.latest_attendance_date', '2026-06-08');

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->whereDate('date', '2026-06-07')
            ->exists())->toBeFalse();
    });

    test('summary includes virtual absent rows for explicit date filters', function () {
        $admin = User::factory()->administrator()->create();
        $missingEmployee = User::factory()->employee()->create([
            'name' => 'Gita Rahma',
            'username' => 'gita',
            'email' => 'gita@example.com',
            'created_at' => '2026-06-01 08:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.summary', [
                'user_id' => $missingEmployee->id,
                'date' => '2026-06-10',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $missingEmployee->id)
            ->assertJsonPath('data.0.absent_count', 1)
            ->assertJsonPath('data.0.total_real_check_ins', 0)
            ->assertJsonPath('data.0.first_attendance_date', '2026-06-10')
            ->assertJsonPath('data.0.latest_attendance_date', '2026-06-10');
    });

    test('summary is administrator only', function () {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->getJson(route('attendances.summary'))
            ->assertForbidden();

        $this->getJson(route('attendances.summary'))
            ->assertForbidden();
    });
});
