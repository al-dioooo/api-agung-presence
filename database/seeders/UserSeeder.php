<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Alice Evergarden',
            'username' => 'aliceevr',
            'email' => 'hello@aliceevr.com',
            'password' => bcrypt('aldio1234'),
            'role' => UserRole::Administrator,
        ]);
    }
}
