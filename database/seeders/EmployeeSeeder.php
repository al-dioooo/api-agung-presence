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
            ['name' => 'Bima Saputra', 'username' => 'bima_saputra', 'email' => 'bima.saputra@agungpresence.test'],
            ['name' => 'Citra Lestari', 'username' => 'citra_lestari', 'email' => 'citra.lestari@agungpresence.test'],
            ['name' => 'Dimas Pratama', 'username' => 'dimas_pratama', 'email' => 'dimas.pratama@agungpresence.test'],
            ['name' => 'Eka Wulandari', 'username' => 'eka_wulandari', 'email' => 'eka.wulandari@agungpresence.test'],
            ['name' => 'Fajar Nugroho', 'username' => 'fajar_nugroho', 'email' => 'fajar.nugroho@agungpresence.test'],
            ['name' => 'Gita Maharani', 'username' => 'gita_maharani', 'email' => 'gita.maharani@agungpresence.test'],
            ['name' => 'Hendra Wijaya', 'username' => 'hendra_wijaya', 'email' => 'hendra.wijaya@agungpresence.test'],
            ['name' => 'Intan Permata', 'username' => 'intan_permata', 'email' => 'intan.permata@agungpresence.test'],
            ['name' => 'Joko Santoso', 'username' => 'joko_santoso', 'email' => 'joko.santoso@agungpresence.test'],
            ['name' => 'Kartika Sari', 'username' => 'kartika_sari', 'email' => 'kartika.sari@agungpresence.test'],
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
