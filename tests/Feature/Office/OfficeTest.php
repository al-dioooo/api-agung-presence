<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    test('authenticated user can list offices', function () {
        Office::factory()->count(3)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('offices.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Offices retrieved successfully.')
            ->assertJsonCount(3, 'data');
    });

    test('can filter only active offices', function () {
        Office::factory()->count(2)->create();
        Office::factory()->inactive()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('offices.index', ['active_only' => true]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot list offices', function () {
        $response = $this->getJson(route('offices.index'));

        $response->assertUnauthorized();
    });
});

describe('store', function () {
    test('administrator can create an office', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('offices.store'), [
                'name' => 'Kantor Baru',
                'address' => 'Jl. Merdeka No. 1',
                'latitude' => -2.965107,
                'longitude' => 104.736443,
                'radius' => 50,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Kantor Baru')
            ->assertJsonPath('data.work_start_time', '08:00')
            ->assertJsonPath('data.work_end_time', '17:00')
            ->assertJsonPath('data.created_by', $admin->username);

        $this->assertDatabaseHas('offices', [
            'name' => 'Kantor Baru',
            'work_start_time' => '08:00',
            'work_end_time' => '17:00',
            'created_by' => $admin->username,
        ]);
    });

    test('employee cannot create an office', function () {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('offices.store'), [
                'name' => 'Kantor Baru',
                'latitude' => -2.965107,
                'longitude' => 104.736443,
            ]);

        $response->assertForbidden();
    });

    test('store validates required fields', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('offices.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'latitude', 'longitude', 'work_start_time', 'work_end_time']);
    });

    test('store validates coordinate bounds', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('offices.store'), [
                'name' => 'Invalid Office',
                'latitude' => 91,
                'longitude' => 181,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    });

    test('store validates working time format and order', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('offices.store'), [
                'name' => 'Invalid Working Time',
                'latitude' => -2.965107,
                'longitude' => 104.736443,
                'work_start_time' => '25:99',
                'work_end_time' => '08:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_start_time']);

        $response = $this->actingAs($admin)
            ->postJson(route('offices.store'), [
                'name' => 'Invalid Working Time',
                'latitude' => -2.965107,
                'longitude' => 104.736443,
                'work_start_time' => '17:00',
                'work_end_time' => '08:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_end_time']);
    });
});

describe('show', function () {
    test('authenticated user can view an office', function () {
        $office = Office::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('offices.show', $office));

        $response->assertOk()
            ->assertJsonPath('data.id', $office->id)
            ->assertJsonPath('data.name', $office->name)
            ->assertJsonPath('data.work_start_time', '08:00')
            ->assertJsonPath('data.work_end_time', '17:00');
    });

    test('returns 404 for non-existent office', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('offices.show', 9999));

        $response->assertNotFound();
    });
});

describe('update', function () {
    test('administrator can update an office', function () {
        $office = Office::factory()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->putJson(route('offices.update', $office), [
                'name' => 'Updated Office',
                'work_start_time' => '09:00',
                'work_end_time' => '18:00',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Office')
            ->assertJsonPath('data.work_start_time', '09:00')
            ->assertJsonPath('data.work_end_time', '18:00')
            ->assertJsonPath('data.updated_by', $admin->username);

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'name' => 'Updated Office',
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'updated_by' => $admin->username,
        ]);
    });

    test('administrator can partially update an office', function () {
        $office = Office::factory()->create(['radius' => 30]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->putJson(route('offices.update', $office), [
                'radius' => 100,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.radius', 100)
            ->assertJsonPath('data.name', $office->name);
    });

    test('employee cannot update an office', function () {
        $office = Office::factory()->create();
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->putJson(route('offices.update', $office), [
                'name' => 'Hacked',
            ]);

        $response->assertForbidden();
    });

    test('update validates coordinate bounds', function () {
        $office = Office::factory()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->putJson(route('offices.update', $office), [
                'latitude' => -100,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    });

    test('update validates working time order', function () {
        $office = Office::factory()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->putJson(route('offices.update', $office), [
                'work_start_time' => '17:00',
                'work_end_time' => '08:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_end_time']);
    });
});

describe('destroy', function () {
    test('administrator can delete an office', function () {
        $office = Office::factory()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->deleteJson(route('offices.destroy', $office));

        $response->assertOk()
            ->assertJsonPath('message', 'Office deleted successfully.');

        $this->assertSoftDeleted('offices', ['id' => $office->id]);
    });

    test('employee cannot delete an office', function () {
        $office = Office::factory()->create();
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->deleteJson(route('offices.destroy', $office));

        $response->assertForbidden();
    });

    test('unauthenticated user cannot delete an office', function () {
        $office = Office::factory()->create();

        $response = $this->deleteJson(route('offices.destroy', $office));

        $response->assertUnauthorized();
    });
});
