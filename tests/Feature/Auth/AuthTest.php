<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

describe('login', function () {
    test('user can login with username', function () {
        $user = User::factory()->create([
            'username' => 'johndoe',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'login' => 'johndoe',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => ['id', 'name', 'username', 'email', 'role', 'created_at'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.id', $user->id);
    });

    test('user can login with email', function () {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'login' => 'john@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    });

    test('login fails with invalid credentials', function () {
        User::factory()->create([
            'username' => 'johndoe',
            'password' => 'correct-password',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'login' => 'johndoe',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    });

    test('login fails with missing fields', function () {
        $response = $this->postJson(route('auth.login'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'password']);
    });

    test('soft deleted user cannot login', function () {
        $user = User::factory()->create([
            'username' => 'deleted_user',
            'password' => 'secret-password',
        ]);
        $user->delete();

        $response = $this->postJson(route('auth.login'), [
            'login' => 'deleted_user',
            'password' => 'secret-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    });
});

describe('logout', function () {
    test('authenticated user can logout', function () {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson(route('auth.logout'));

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        expect($user->fresh()->tokens)->toHaveCount(0);
    });

    test('unauthenticated user cannot logout', function () {
        $response = $this->postJson(route('auth.logout'));

        $response->assertUnauthorized();
    });
});

describe('me', function () {
    test('authenticated user can get their profile', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('auth.me'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'username', 'email', 'role', 'created_at'],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username);
    });

    test('unauthenticated user cannot access profile', function () {
        $response = $this->getJson(route('auth.me'));

        $response->assertUnauthorized();
    });

    test('authenticated user can update their profile username and email', function () {
        $user = User::factory()->create([
            'username' => 'old_user',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('auth.me.update'), [
                'username' => 'new_user',
                'email' => 'new@example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'User profile updated.')
            ->assertJsonPath('data.username', 'new_user')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'new_user',
            'email' => 'new@example.com',
        ]);
    });

    test('authenticated user can update their password', function () {
        $user = User::factory()->create([
            'username' => 'password_user',
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('auth.me.update'), [
                'password' => 'new-password',
            ]);

        $response->assertOk();

        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    });

    test('profile update validates duplicate username and email', function () {
        User::factory()->create([
            'username' => 'taken_user',
            'email' => 'taken@example.com',
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson(route('auth.me.update'), [
                'username' => 'taken_user',
                'email' => 'taken@example.com',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email']);
    });

    test('profile update validates username rules', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson(route('auth.me.update'), [
                'username' => 'invalid username',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    });

    test('unauthenticated user cannot update profile', function () {
        $response = $this->patchJson(route('auth.me.update'), [
            'username' => 'blocked',
        ]);

        $response->assertUnauthorized();
    });
});

describe('register', function () {
    test('administrator can register a new user', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('auth.register'), [
                'name' => 'New Employee',
                'username' => 'newemployee',
                'email' => 'new@example.com',
                'password' => 'secure-password',
                'role' => UserRole::Employee->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.username', 'newemployee')
            ->assertJsonPath('data.user.role', UserRole::Employee->value);

        $this->assertDatabaseHas('users', [
            'username' => 'newemployee',
            'email' => 'new@example.com',
        ]);
    });

    test('administrator can register another administrator', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('auth.register'), [
                'name' => 'New Admin',
                'username' => 'newadmin',
                'email' => 'admin2@example.com',
                'password' => 'secure-password',
                'role' => UserRole::Administrator->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::Administrator->value);
    });

    test('employee cannot register a new user', function () {
        $employee = User::factory()->employee()->create();

        $response = $this->actingAs($employee)
            ->postJson(route('auth.register'), [
                'name' => 'Someone',
                'username' => 'someone',
                'email' => 'someone@example.com',
                'password' => 'secure-password',
                'role' => UserRole::Employee->value,
            ]);

        $response->assertForbidden();
    });

    test('unauthenticated user cannot register', function () {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Someone',
            'username' => 'someone',
            'email' => 'someone@example.com',
            'password' => 'secure-password',
            'role' => UserRole::Employee->value,
        ]);

        $response->assertUnauthorized();
    });

    test('registration validates required fields', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('auth.register'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'username', 'email', 'password', 'role']);
    });

    test('registration validates unique username and email', function () {
        $admin = User::factory()->administrator()->create();
        $existing = User::factory()->create([
            'username' => 'taken',
            'email' => 'taken@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('auth.register'), [
                'name' => 'Duplicate',
                'username' => 'taken',
                'email' => 'taken@example.com',
                'password' => 'secure-password',
                'role' => UserRole::Employee->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email']);
    });

    test('registration validates role is valid enum value', function () {
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)
            ->postJson(route('auth.register'), [
                'name' => 'Someone',
                'username' => 'someone',
                'email' => 'someone@example.com',
                'password' => 'secure-password',
                'role' => 'invalid-role',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });
});

describe('seeders', function () {
    test('default administrator uses configured credentials', function () {
        $this->seed(UserSeeder::class);

        $user = User::where('username', 'angelika')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Septia Angelika')
            ->and($user->email)->toBe('hello@angeldaely.com')
            ->and($user->role)->toBe(UserRole::Administrator)
            ->and(Hash::check('angelika', $user->password))->toBeTrue();
    });
});
