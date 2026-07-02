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

function proofPhoto(): string
{
    return 'data:image/png;base64,'.base64_encode('proof');
}

afterEach(function () {
    Carbon::setTestNow();
});

describe('index', function () {
    test('administrator can list all attendance requests', function () {
        $admin = User::factory()->administrator()->create();
        AttendanceRequest::factory()->count(2)->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendance-requests.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance requests retrieved successfully.')
            ->assertJsonCount(2, 'data');
    });

    test('employee can only list their own attendance requests', function () {
        $employee = User::factory()->employee()->create();
        AttendanceRequest::factory()->count(2)->create(['user_id' => $employee->id]);
        AttendanceRequest::factory()->count(3)->create();

        $response = $this->actingAs($employee)
            ->getJson(route('attendance-requests.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $attendanceRequest) {
            expect($attendanceRequest['user_id'])->toBe($employee->id);
        }
    });
});

describe('store', function () {
    test('employee can submit a permit request with date range, description, and proof photo', function () {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Permit->value,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
                'description' => 'Mengurus keperluan keluarga.',
                'proof_photo' => proofPhoto(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Attendance request created successfully.')
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.type', AttendanceStatus::Permit->value)
            ->assertJsonPath('data.start_date', '2026-06-01')
            ->assertJsonPath('data.end_date', '2026-06-03')
            ->assertJsonPath('data.workday_count', 3)
            ->assertJsonPath('data.approval_status', AttendanceRequestStatus::Pending->value)
            ->assertJsonPath('data.created_by', $employee->username);

        $this->assertDatabaseHas('attendance_requests', [
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Permit->value,
            'approval_status' => AttendanceRequestStatus::Pending->value,
            'created_by' => $employee->username,
        ]);
    });

    test('store validates required fields and date range', function () {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::OnTime->value,
                'start_date' => '2026-06-05',
                'end_date' => '2026-06-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'end_date',
                'description',
                'proof_photo',
            ]);
    });

    test('administrator cannot submit employee attendance requests', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Sick->value,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-01',
                'description' => 'Sakit.',
                'proof_photo' => proofPhoto(),
            ]);

        $response->assertForbidden();
    });

    test('employee cannot submit overlapping pending or approved requests', function () {
        $employee = User::factory()->employee()->create();
        AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'approval_status' => AttendanceRequestStatus::Pending,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Leave->value,
                'start_date' => '2026-06-03',
                'end_date' => '2026-06-04',
                'description' => 'Cuti keluarga.',
                'proof_photo' => proofPhoto(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    });

    test('employee cannot request dates that already contain real check ins', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-06-02',
            'status' => AttendanceStatus::OnTime,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Sick->value,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
                'description' => 'Sakit.',
                'proof_photo' => proofPhoto(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    });

    test('sick request cannot start after today but can end in the future', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-10 08:00:00', 'Asia/Jakarta'));
        $employee = User::factory()->employee()->create();

        $futureStartResponse = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Sick->value,
                'start_date' => '2026-06-11',
                'end_date' => '2026-06-12',
                'description' => 'Sakit.',
                'proof_photo' => proofPhoto(),
            ]);

        $futureStartResponse->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);

        $todayStartResponse = $this->actingAs($employee)
            ->postJson(route('attendance-requests.store'), [
                'type' => AttendanceStatus::Sick->value,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'description' => 'Sakit.',
                'proof_photo' => proofPhoto(),
            ]);

        $todayStartResponse->assertCreated()
            ->assertJsonPath('data.start_date', '2026-06-10')
            ->assertJsonPath('data.end_date', '2026-06-12');

        Carbon::setTestNow();
    });
});

describe('show', function () {
    test('administrator can view any attendance request', function () {
        $admin = User::factory()->administrator()->create();
        $attendanceRequest = AttendanceRequest::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendance-requests.show', $attendanceRequest));

        $response->assertOk()
            ->assertJsonPath('data.id', $attendanceRequest->id)
            ->assertJsonPath('data.user.id', $attendanceRequest->user_id);
    });

    test('employee can view only their own attendance request', function () {
        $employee = User::factory()->employee()->create();
        $ownRequest = AttendanceRequest::factory()->create(['user_id' => $employee->id]);
        $otherRequest = AttendanceRequest::factory()->create();

        $this->actingAs($employee)
            ->getJson(route('attendance-requests.show', $ownRequest))
            ->assertOk()
            ->assertJsonPath('data.id', $ownRequest->id);

        $this->actingAs($employee)
            ->getJson(route('attendance-requests.show', $otherRequest))
            ->assertForbidden();
    });
});

describe('review', function () {
    test('administrator can approve an attendance request and create one attendance per workday', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Permit,
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-08',
            'proof_photo' => proofPhoto(),
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance request approved successfully.')
            ->assertJsonPath('data.approval_status', AttendanceRequestStatus::Approved->value)
            ->assertJsonPath('data.workday_count', 3)
            ->assertJsonPath('data.reviewed_by', $admin->id);

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->count())->toBe(3);

        foreach (['2026-06-05', '2026-06-06', '2026-06-08'] as $date) {
            expect(Attendance::query()
                ->where('attendance_request_id', $attendanceRequest->id)
                ->where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->where('status', AttendanceStatus::Permit->value)
                ->whereNull('office_id')
                ->whereNull('in_at')
                ->where('created_by', $admin->username)
                ->exists())->toBeTrue();
        }

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->whereDate('date', '2026-06-07')
            ->exists())->toBeFalse();
    });

    test('administrator approval updates existing non-real attendance instead of duplicating', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Leave,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
        ]);
        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => null,
            'date' => '2026-06-01',
            'in_at' => null,
            'out_at' => null,
            'in_latitude' => null,
            'in_longitude' => null,
            'status' => AttendanceStatus::Sick,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ])
            ->assertOk();

        expect(Attendance::query()
            ->where('user_id', $employee->id)
            ->whereDate('date', '2026-06-01')
            ->count())->toBe(1);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'attendance_request_id' => $attendanceRequest->id,
            'status' => AttendanceStatus::Leave->value,
            'updated_by' => $admin->username,
        ]);
    });

    test('administrator approval only materializes arrived request dates', function () {
        Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00', 'Asia/Jakarta'));
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Permit,
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'proof_photo' => proofPhoto(),
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.workday_count', 3);

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->count())->toBe(1);

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->whereDate('date', '2026-06-10')
            ->where('status', AttendanceStatus::Permit->value)
            ->exists())->toBeTrue()
            ->and(Attendance::query()
                ->where('attendance_request_id', $attendanceRequest->id)
                ->whereDate('date', '2026-06-11')
                ->exists())->toBeFalse();

        Carbon::setTestNow();
    });

    test('administrator can reject an attendance request with a reason', function () {
        $admin = User::factory()->administrator()->create();
        $attendanceRequest = AttendanceRequest::factory()->create();

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Rejected->value,
                'rejection_reason' => 'Bukti tidak sesuai.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance request rejected successfully.')
            ->assertJsonPath('data.approval_status', AttendanceRequestStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Bukti tidak sesuai.');

        expect(Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->count())->toBe(0);
    });

    test('rejection requires a reason', function () {
        $admin = User::factory()->administrator()->create();
        $attendanceRequest = AttendanceRequest::factory()->create();

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Rejected->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rejection_reason']);
    });

    test('employee cannot review attendance requests', function () {
        $employee = User::factory()->employee()->create();
        $attendanceRequest = AttendanceRequest::factory()->create(['user_id' => $employee->id]);

        $response = $this->actingAs($employee)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ]);

        $response->assertForbidden();
    });

    test('already reviewed attendance requests cannot be reviewed again', function () {
        $admin = User::factory()->administrator()->create();
        $attendanceRequest = AttendanceRequest::factory()
            ->approved($admin)
            ->create();

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Rejected->value,
                'rejection_reason' => 'Ditolak.',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.approval_status.0', 'Attendance request has already been reviewed.');
    });

    test('approval fails when a real check in appears after submission', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-06-02',
            'status' => AttendanceStatus::Late,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.start_date.0', 'Request range contains an existing real check-in attendance.');
    });

    test('approved attendance detail includes the linked request summary', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $attendanceRequest = AttendanceRequest::factory()->create([
            'user_id' => $employee->id,
            'type' => AttendanceStatus::Sick,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'description' => 'Demam.',
            'proof_photo' => proofPhoto(),
        ]);

        $this->actingAs($admin)
            ->patchJson(route('attendance-requests.review', $attendanceRequest), [
                'approval_status' => AttendanceRequestStatus::Approved->value,
            ])
            ->assertOk();

        $attendance = Attendance::query()
            ->where('attendance_request_id', $attendanceRequest->id)
            ->firstOrFail();

        $this->actingAs($employee)
            ->getJson(route('attendances.show', $attendance))
            ->assertOk()
            ->assertJsonPath('data.attendance_request.id', $attendanceRequest->id)
            ->assertJsonPath('data.attendance_request.description', 'Demam.')
            ->assertJsonPath('data.attendance_request.workday_count', 1)
            ->assertJsonPath('data.attendance_request.reviewed_by', $admin->id);
    });
});
