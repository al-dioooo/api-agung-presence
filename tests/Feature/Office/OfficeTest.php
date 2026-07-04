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

    test('office list is paginated by default', function () {
        Office::factory()->count(20)->sequence(
            fn ($sequence) => ['name' => sprintf('Office %02d', $sequence->index + 1)],
        )->create();
        $admin = User::factory()->administrator()->create();

        $firstPage = $this->actingAs($admin)
            ->getJson(route('offices.index'));

        $firstPage->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 15);

        $secondPage = $this->actingAs($admin)
            ->getJson(route('offices.index', ['page' => 2]));

        $secondPage->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.from', 16)
            ->assertJsonPath('meta.to', 20);
    });

    test('can filter only active offices', function () {
        Office::factory()->count(2)->create();
        Office::factory()->inactive()->create();
        $user = User::factory()->administrator()->create();

        $response = $this->actingAs($user)
            ->getJson(route('offices.index', ['active_only' => true]));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('can filter active offices using string boolean query values', function () {
        Office::factory()->count(2)->create();
        Office::factory()->inactive()->create();
        $admin = User::factory()->administrator()->create();

        $activeOnlyResponse = $this->actingAs($admin)
            ->getJson(route('offices.index', ['active_only' => 'true']));

        $activeOnlyResponse->assertOk()
            ->assertJsonCount(2, 'data');

        $allResponse = $this->actingAs($admin)
            ->getJson(route('offices.index', ['active_only' => 'false']));

        $allResponse->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('employee only sees active offices', function () {
        Office::factory()->count(2)->create();
        Office::factory()->inactive()->create();
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->getJson(route('offices.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('administrator sees inactive offices by default', function () {
        Office::factory()->count(2)->create();
        Office::factory()->inactive()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.index'));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('can search offices by name or address', function () {
        Office::factory()->create([
            'name' => 'Kampus Sudirman',
            'address' => 'Jl. Sudirman',
        ]);
        Office::factory()->create([
            'name' => 'Kampus Merdeka',
            'address' => 'Jl. Merdeka',
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.index', ['search' => 'sudirman']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kampus Sudirman');
    });

    test('administrator can filter offices by active status', function () {
        Office::factory()->create(['name' => 'Active Office']);
        Office::factory()->inactive()->create(['name' => 'Inactive Office']);
        $admin = User::factory()->administrator()->create();

        $activeResponse = $this->actingAs($admin)
            ->getJson(route('offices.index', ['active_status' => 'active']));

        $activeResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Office')
            ->assertJsonPath('data.0.is_active', true);

        $inactiveResponse = $this->actingAs($admin)
            ->getJson(route('offices.index', ['active_status' => 'inactive']));

        $inactiveResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Inactive Office')
            ->assertJsonPath('data.0.is_active', false);
    });

    test('employee active office scope overrides active status filters', function (string $activeStatus) {
        Office::factory()->create();
        Office::factory()->inactive()->create();
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->getJson(route('offices.index', ['active_status' => $activeStatus]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', true);
    })->with(['all', 'inactive']);

    test('can sort offices by nearest current location', function () {
        Office::factory()->create([
            'name' => 'Far Office',
            'latitude' => -2.990000,
            'longitude' => 104.760000,
        ]);
        Office::factory()->create([
            'name' => 'Near Office',
            'latitude' => -2.970100,
            'longitude' => 104.740100,
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.index', [
                'sort' => 'nearest',
                'latitude' => -2.970000,
                'longitude' => 104.740000,
            ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Near Office')
            ->assertJsonPath('data.1.name', 'Far Office');

        expect($response->json('data.0.distance_meters'))->toBeFloat()
            ->and($response->json('data.1.distance_meters'))->toBeFloat()
            ->and($response->json('data.0.distance_meters'))->toBeLessThan($response->json('data.1.distance_meters'));
    });

    test('nearest office sorting applies limit after distance ordering', function () {
        Office::factory()->create([
            'name' => 'Far Office',
            'latitude' => -2.990000,
            'longitude' => 104.760000,
        ]);
        Office::factory()->create([
            'name' => 'Near Office',
            'latitude' => -2.970100,
            'longitude' => 104.740100,
        ]);
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.index', [
                'sort' => 'nearest',
                'latitude' => -2.970000,
                'longitude' => 104.740000,
                'limit' => 1,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Near Office');
    });

    test('office filters validate active status nearest coordinates limit and pagination', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.index', [
                'active_status' => 'archived',
                'sort' => 'nearest',
                'latitude' => 91,
                'limit' => 101,
                'page' => 0,
                'per_page' => 101,
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['active_status', 'latitude', 'longitude', 'limit', 'page', 'per_page']);
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

    test('employee cannot view inactive office', function () {
        $office = Office::factory()->inactive()->create();
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->getJson(route('offices.show', $office));

        $response->assertNotFound();
    });

    test('administrator can view inactive office', function () {
        $office = Office::factory()->inactive()->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('offices.show', $office));

        $response->assertOk()
            ->assertJsonPath('data.id', $office->id)
            ->assertJsonPath('data.is_active', false);
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
