<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'Kantor Pusat Sudirman',
                'address' => 'Jl. Jend. Sudirman, 24 Ilir, Bukit Kecil, Palembang',
                'latitude' => '-2.97607300',
                'longitude' => '104.74687200',
                'radius' => 75,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'photo' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Kantor Ilir Barat',
                'address' => 'Jl. Demang Lebar Daun, Ilir Barat I, Palembang',
                'latitude' => '-2.96470000',
                'longitude' => '104.72850000',
                'radius' => 80,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'photo' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Kantor Jakabaring',
                'address' => 'Jl. Gubernur H. Bastari, Jakabaring, Palembang',
                'latitude' => '-3.02450000',
                'longitude' => '104.78260000',
                'radius' => 100,
                'work_start_time' => '08:30',
                'work_end_time' => '17:30',
                'photo' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Kantor Seberang Ulu',
                'address' => 'Jl. KH. Wahid Hasyim, Seberang Ulu I, Palembang',
                'latitude' => '-3.01190000',
                'longitude' => '104.76970000',
                'radius' => 90,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'photo' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Kantor Kertapati',
                'address' => 'Jl. Ki Merogan, Kertapati, Palembang',
                'latitude' => '-3.01100000',
                'longitude' => '104.74100000',
                'radius' => 85,
                'work_start_time' => '08:00',
                'work_end_time' => '17:00',
                'photo' => null,
                'is_active' => true,
            ],
        ];

        foreach ($data as $item) {
            $office = Office::withTrashed()->updateOrCreate(
                ['name' => $item['name']],
                $item,
            );

            if ($office->trashed()) {
                $office->restore();
            }
        }
    }
}
