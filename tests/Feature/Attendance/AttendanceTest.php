<?php

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    test('administrator can list all attendances', function () {
        Attendance::factory()->count(3)->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Attendances retrieved successfully.')
            ->assertJsonCount(3, 'data');
    });

    test('employee can only list their own attendances', function () {
        $employee = User::factory()->employee()->create();
        Attendance::factory()->count(2)->create(['user_id' => $employee->id]);
        Attendance::factory()->count(3)->create(); // Other users' attendances

        $response = $this->actingAs($employee)
            ->getJson(route('attendances.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot list attendances', function () {
        $response = $this->getJson(route('attendances.index'));

        $response->assertUnauthorized();
    });
});

describe('store', function () {
    test('employee can check in (create attendance)', function () {
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('attendances.store'), [
                'office_id' => $office->id,
                'in_latitude' => -2.965107,
                'in_longitude' => 104.736443,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $employee->id)
            ->assertJsonPath('data.office_id', $office->id);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'office_id' => $office->id,
            'created_by' => $employee->username,
        ]);
    });

    test('administrator can create attendance for another user', function () {
        $admin = User::factory()->administrator()->create();
        $employee = User::factory()->employee()->create();
        $office = Office::factory()->create();

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
        $office = Office::factory()->create();

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
});

describe('show', function () {
    test('administrator can view any attendance', function () {
        $admin = User::factory()->administrator()->create();
        $attendance = Attendance::factory()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.show', $attendance));

        $response->assertOk()
            ->assertJsonPath('data.id', $attendance->id);
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
