<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('user resource authorization', function () {
    test('administrator can list users', function () {
        User::factory()->employee()->count(2)->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Users retrieved successfully.')
            ->assertJsonCount(3, 'data');
    });

    test('administrator can view a user', function () {
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.show', $employee));

        $response->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.role', UserRole::Employee->value);
    });

    test('administrator can update a user', function () {
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->patchJson(route('users.update', $employee), [
                'name' => 'Updated Employee',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Employee');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Updated Employee',
        ]);
    });

    test('administrator can delete a user', function () {
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->deleteJson(route('users.destroy', $employee));

        $response->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $employee->id]);
    });

    test('administrator can soft delete a user with attendances', function () {
        $employee = User::factory()->employee()->create([
            'name' => 'Deleted Employee',
            'username' => 'deleted_employee',
            'email' => 'deleted.employee@example.com',
        ]);
        $attendance = Attendance::factory()->create(['user_id' => $employee->id]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->deleteJson(route('users.destroy', $employee));

        $response->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);

        $response = $this->actingAs($admin)
            ->getJson(route('attendances.show', $attendance));

        $response->assertOk()
            ->assertJsonPath('data.user.name', 'Deleted Employee')
            ->assertJsonPath('data.user.username', 'deleted_employee')
            ->assertJsonPath('data.user.email', 'deleted.employee@example.com');
    });

    test('deleting a user revokes their tokens', function () {
        $employee = User::factory()->employee()->create();
        $token = $employee->createToken('api-token')->plainTextToken;
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)
            ->deleteJson(route('users.destroy', $employee))
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    });

    test('employee cannot access user resource routes', function (string $method, string $routeName) {
        $employee = User::factory()->employee()->create();
        $target = User::factory()->employee()->create();
        $payload = $method === 'PATCH' ? ['name' => 'Blocked'] : [];

        $response = $this->actingAs($employee)
            ->json($method, route($routeName, str_contains($routeName, '.index') ? [] : [$target]), $payload);

        $response->assertForbidden();
    })->with([
        ['GET', 'users.index'],
        ['GET', 'users.show'],
        ['PATCH', 'users.update'],
        ['DELETE', 'users.destroy'],
    ]);
});
