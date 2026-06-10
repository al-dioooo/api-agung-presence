<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    test('administrator can list all attendances', function () {
        $firstEmployee = User::factory()->employee()->create([
            'name' => 'First Employee',
            'username' => 'first_employee',
            'email' => 'first.employee@example.com',
        ]);
        $secondEmployee = User::factory()->employee()->create();
        Attendance::factory()->create([
            'user_id' => $firstEmployee->id,
            'date' => '2026-05-28',
            'in_at' => '2026-05-28 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $secondEmployee->id,
            'date' => '2026-05-27',
            'in_at' => '2026-05-27 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $firstEmployee->id,
            'date' => '2026-05-26',
            'in_at' => '2026-05-26 08:00:00',
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendances retrieved successfully.')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.user.name', 'First Employee')
            ->assertJsonPath('data.0.user.username', 'first_employee')
            ->assertJsonPath('data.0.user.email', 'first.employee@example.com');
    });

    test('employee can only list their own attendances', function () {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->count(2)->create(['user_id' => $employee->id]);
        Attendance::factory()->count(3)->create(); // Other users' attendances

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $attendance) {
            expect($attendance['user_id'])->toBe($employee->id);
        }
    });

    test('employee cannot use filters to list other users attendances', function () {
        $employee = User::factory()->employee()->create();
        $otherUser = User::factory()->employee()->create();
        $office = Office::factory()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-05-28',
        ]);
        Attendance::factory()->create([
            'user_id' => $otherUser->id,
            'office_id' => $office->id,
            'date' => '2026-05-28',
        ]);

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.index', [
                'office_id' => $office->id,
                'date' => '2026-05-28',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $employee->id);
    });

    test('can filter attendances by office and date', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'date' => '2026-05-28',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-27',
        ]);

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.index', [
                'office_id' => $office->id,
                'date' => '2026-05-28',
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.office_id', $office->id)
            ->assertJsonPath('data.0.date', '2026-05-28');
    });

    test('can filter attendances by inclusive date range', function () {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-26',
            'in_at' => '2026-05-26 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-27',
            'in_at' => '2026-05-27 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-28',
            'in_at' => '2026-05-28 08:00:00',
        ]);

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.index', [
                'start_date' => '2026-05-27',
                'end_date' => '2026-05-28',
            ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-05-28')
            ->assertJsonPath('data.1.date', '2026-05-27');
    });

    test('can filter attendances by open-ended date ranges', function () {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-26',
            'in_at' => '2026-05-26 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-27',
            'in_at' => '2026-05-27 08:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-05-28',
            'in_at' => '2026-05-28 08:00:00',
        ]);

        $startResponse = $this->actingAs($employee)
            ->getJson(route('attendances.index', [
                'start_date' => '2026-05-27',
            ]));

        $startResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-05-28')
            ->assertJsonPath('data.1.date', '2026-05-27');

        $endResponse = $this->actingAs($employee)
            ->getJson(route('attendances.index', [
                'end_date' => '2026-05-27',
            ]));

        $endResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-05-27')
            ->assertJsonPath('data.1.date', '2026-05-26');
    });

    test('unauthenticated user cannot list attendances', function () {
        $response = $this->getJson(route('attendances.index'));

        $response->assertUnauthorized();
    });
});

describe('store', function () {
    test('employee can check in (create attendance)', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_at' => '2026-05-28 08:01:00',
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', AttendanceStatus::Late->value);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'status' => AttendanceStatus::Late->value,
            'created_by' => $employee->username,
        ]);
    });

    test('administrator can create attendance for another user', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('attendances.store'), [
                'user_id' => $employee->id,
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id);
    });

    test('employee cannot provide user_id (it gets overridden by their own)', function () {
        $employee = User::factory()->employee()->create();
        $otherUser = User::factory()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'user_id' => $otherUser->id,
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        // It should still be created but for the logged-in employee, not $otherUser
        $response->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id);
    });

    test('store validates required fields', function () {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id', 'in_latitude', 'in_longitude']);
    });

    test('check in stores on time status when arrival is not after office start time', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
            'work_start_time' => '08:00',
            'work_end_time' => '17:00',
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_at' => '2026-05-28 08:00:00',
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
                'status' => AttendanceStatus::Late->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AttendanceStatus::OnTime->value);
    });

    test('check in stores late status when arrival is after office start time', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
            'work_start_time' => '08:00',
            'work_end_time' => '17:00',
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_at' => '2026-05-28 08:01:00',
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
                'status' => AttendanceStatus::OnTime->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AttendanceStatus::Late->value);
    });

    test('employee cannot check in at an inactive office', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->inactive()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.office_id.0', 'Office is inactive.');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
        ]);
    });

    test('employee cannot check in outside office radius', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_latitude' => -2.975107,
                'in_longitude' => 104.746443,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.in_latitude.0', 'Attendance location is outside the office radius.');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
        ]);
    });

    test('administrator cannot create attendance at an inactive office', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->inactive()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('attendances.store'), [
                'user_id' => $employee->id,
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.office_id.0', 'Office is inactive.');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
        ]);
    });

    test('administrator cannot create attendance outside office radius', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('attendances.store'), [
                'user_id' => $employee->id,
                'office_id' => $office->id,
                'in_latitude' => -2.975107,
                'in_longitude' => 104.746443,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.in_latitude.0', 'Attendance location is outside the office radius.');

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
        ]);
    });

    test('employee cannot check in while another attendance is active', function () {
        $employee = User::factory()->employee()->create();
        $firstOffice = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);
        $secondOffice = Office::factory()->create([
            'latitude' => -2.964900,
            'longitude' => 104.736300,
            'radius' => 50,
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $firstOffice->id,
            'out_at' => null,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $secondOffice->id,
                'in_latitude' => -2.964900,
                'in_longitude' => 104.736300,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.user_id.0', 'User already has an active attendance. Please check out first.');

        expect(Attendance::query()->where('user_id', $employee->id)->whereNull('out_at')->count())->toBe(1);
    });

    test('employee can check in again after active attendance is checked out', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'out_at' => '2026-05-28 17:00:00',
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.office_id', $office->id);
    });

    test('administrator cannot create attendance for a user with active attendance', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create([
            'latitude' => -2.965107,
            'longitude' => 104.736443,
            'radius' => 50,
        ]);
        Attendance::factory()->create([
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'out_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('attendances.store'), [
                'user_id' => $employee->id,
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.user_id.0', 'User already has an active attendance. Please check out first.');
    });
});

describe('show', function () {
    test('administrator can view any attendance', function () {
        $admin = User::factory()->administrator()->create();
        $attendance = Attendance::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.show', $attendance));

        $response->assertOk()
            ->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.user.name', $attendance->user->name)
            ->assertJsonPath('data.user.username', $attendance->user->username)
            ->assertJsonPath('data.user.email', $attendance->user->email);
    });

    test('employee can view their own attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(['user_id' => $employee->id]);

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.show', $attendance));

        $response->assertOk()
            ->assertJsonPath('data.id', $attendance->id);
    });

    test('employee cannot view other users attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(); // Belongs to someone else

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.show', $attendance));

        $response->assertForbidden();
    });
});

describe('update', function () {
    test('administrator can update any attendance', function () {
        $admin = User::factory()->administrator()->create();
        $attendance = Attendance::factory()->create();

        $response = $this->actingAs($admin)
            ->putJson(route('attendances.update', $attendance), [
                'status' => AttendanceStatus::Late->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', AttendanceStatus::Late->value)
            ->assertJsonPath('data.updated_by', $admin->username);
    });

    test('employee can update their own attendance (check out)', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(['user_id' => $employee->id]);
        $outAt = now()->toIso8601String();

        $response = $this->actingAs($employee)
            ->putJson(route('attendances.update', $attendance), [
                'out_at' => $outAt,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.out_at', $outAt);
    });

    test('employee cannot update other users attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(); // Someone else's

        $response = $this->actingAs($employee)
            ->putJson(route('attendances.update', $attendance), [
                'out_at' => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertForbidden();
    });
});

describe('checkout', function () {
    test('employee can check out their own active attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'out_at' => null,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.checkout', $attendance));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance checked out successfully.')
            ->assertJsonPath('data.updated_by', $employee->username);

        expect($response->json('data.out_at'))->not->toBeNull();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
            'out_at' => null,
        ]);
    });

    test('administrator can check out any active attendance', function () {
        $admin = User::factory()->administrator()->create();
        $attendance = Attendance::factory()->create(['out_at' => null]);

        $response = $this->actingAs($admin)
            ->postJson(route('attendances.checkout', $attendance));

        $response->assertOk()
            ->assertJsonPath('data.updated_by', $admin->username);

        expect($response->json('data.out_at'))->not->toBeNull();
    });

    test('employee cannot check out other users attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(['out_at' => null]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.checkout', $attendance));

        $response->assertForbidden();
    });

    test('checkout rejects attendance that is already checked out', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create([
            'user_id' => $employee->id,
            'out_at' => '2026-05-28 17:00:00',
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.checkout', $attendance));

        $response->assertUnprocessable()
            ->assertJsonPath('data.errors.out_at.0', 'Attendance has already been checked out.');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'out_at' => '2026-05-28 17:00:00',
        ]);
    });
});

describe('destroy', function () {
    test('administrator can delete an attendance', function () {
        $admin = User::factory()->administrator()->create();
        $attendance = Attendance::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson(route('attendances.destroy', $attendance));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendance deleted successfully.');

        $this->assertSoftDeleted('attendances', ['id' => $attendance->id]);
    });

    test('employee cannot delete an attendance', function () {
        $employee = User::factory()->employee()->create();
        $attendance = Attendance::factory()->create(['user_id' => $employee->id]);

        $response = $this->actingAs($employee)
            ->deleteJson(route('attendances.destroy', $attendance));

        $response->assertForbidden();
    });
});
