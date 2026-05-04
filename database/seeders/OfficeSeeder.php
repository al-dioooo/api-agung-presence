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
                'name' => 'Kantor Pusat',
                'address' => 'Jl. Basuki Rahmat No. 123',
                'latitude' => '-2.9651078843454405',
                'longitude' => '104.73644385674609',
                'radius' => '50',
                'photo' => null,
                'is_active' => true,
            ],
        ];

        foreach ($data as $item) {
            Office::create($item);
        }
    }
}
