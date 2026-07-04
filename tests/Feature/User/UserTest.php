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

    test('user list is paginated by default', function () {
        User::factory()->employee()->count(20)->sequence(
            fn ($sequence) => ['name' => sprintf('Employee %02d', $sequence->index + 1)],
        )->create();
        $admin = User::factory()->administrator()->create(['name' => 'Admin User']);

        $firstPage = $this->actingAs($admin)
            ->getJson(route('users.index', ['role' => UserRole::Employee->value]));

        $firstPage->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.last_page', 2);

        $secondPage = $this->actingAs($admin)
            ->getJson(route('users.index', [
                'role' => UserRole::Employee->value,
                'page' => 2,
            ]));

        $secondPage->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.from', 16)
            ->assertJsonPath('meta.to', 20);
    });

    test('administrator can search users by identity fields', function () {
        User::factory()->employee()->create([
            'name' => 'Maya Lestari',
            'username' => 'maya_lestari',
            'email' => 'maya@example.com',
        ]);
        User::factory()->employee()->create([
            'name' => 'Rafi Pratama',
            'username' => 'rafi_pratama',
            'email' => 'rafi@example.com',
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.index', ['search' => 'maya']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maya Lestari');
    });

    test('administrator can filter users by role', function () {
        User::factory()->employee()->count(2)->create();
        User::factory()->administrator()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.index', ['role' => UserRole::Employee->value]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $user) {
            expect($user['role'])->toBe(UserRole::Employee->value);
        }
    });

    test('administrator can combine user search role and limit filters', function () {
        User::factory()->employee()->create([
            'name' => 'Dina Permata',
            'username' => 'dina_permata',
        ]);
        User::factory()->employee()->create([
            'name' => 'Dina Sari',
            'username' => 'dina_sari',
        ]);
        User::factory()->administrator()->create([
            'name' => 'Dina Admin',
            'username' => 'dina_admin',
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.index', [
                'search' => 'dina',
                'role' => UserRole::Employee->value,
                'limit' => 1,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', UserRole::Employee->value);
    });

    test('user list validates role limit and pagination filters', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('users.index', [
                'role' => 'owner',
                'limit' => 101,
                'page' => 0,
                'per_page' => 101,
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'limit', 'page', 'per_page']);
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
