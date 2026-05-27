<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = [
            ['name' => 'Bima Saputra', 'username' => 'bima.saputra', 'email' => 'bima.saputra@agungpresence.test'],
            ['name' => 'Citra Lestari', 'username' => 'citra.lestari', 'email' => 'citra.lestari@agungpresence.test'],
            ['name' => 'Dimas Pratama', 'username' => 'dimas.pratama', 'email' => 'dimas.pratama@agungpresence.test'],
            ['name' => 'Eka Wulandari', 'username' => 'eka.wulandari', 'email' => 'eka.wulandari@agungpresence.test'],
            ['name' => 'Fajar Nugroho', 'username' => 'fajar.nugroho', 'email' => 'fajar.nugroho@agungpresence.test'],
            ['name' => 'Gita Maharani', 'username' => 'gita.maharani', 'email' => 'gita.maharani@agungpresence.test'],
            ['name' => 'Hendra Wijaya', 'username' => 'hendra.wijaya', 'email' => 'hendra.wijaya@agungpresence.test'],
            ['name' => 'Intan Permata', 'username' => 'intan.permata', 'email' => 'intan.permata@agungpresence.test'],
            ['name' => 'Joko Santoso', 'username' => 'joko.santoso', 'email' => 'joko.santoso@agungpresence.test'],
            ['name' => 'Kartika Sari', 'username' => 'kartika.sari', 'email' => 'kartika.sari@agungpresence.test'],
        ];

        foreach ($employees as $employee) {
            $user = User::updateOrCreate(
                ['username' => $employee['username']],
                [
                    ...$employee,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Employee,
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
