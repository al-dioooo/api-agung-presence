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
            'name' => 'Septia Angelika',
            'username' => 'angelika',
            'email' => 'hello@angeldaely.com',
            'password' => bcrypt('angelika'),
            'role' => UserRole::Administrator,
        ]);
    }
}
