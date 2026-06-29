<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
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

    test('summary is administrator only', function () {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->getJson(route('attendances.summary'))
            ->assertForbidden();

        $this->getJson(route('attendances.summary'))
            ->assertForbidden();
    });
});
