<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = User::query()
            ->where('role', 'employee')
            ->where('email', 'like', '%@agungpresence.test')
            ->orderBy('username')
            ->get();

        $offices = Office::query()
            ->whereIn('name', [
                'Kantor Pusat Sudirman',
                'Kantor Ilir Barat',
                'Kantor Jakabaring',
                'Kantor Seberang Ulu',
                'Kantor Kertapati',
            ])
            ->orderBy('name')
            ->get()
            ->values();

        if ($employees->isEmpty() || $offices->isEmpty()) {
            return;
        }

        $today = CarbonImmutable::today();

        Attendance::query()
            ->where('created_by', 'demo-seeder')
            ->forceDelete();

        foreach ($employees->values() as $employeeIndex => $employee) {
            foreach (range(7, 0) as $dayOffset) {
                $date = $today->subDays($dayOffset);
                $office = $offices[($employeeIndex + $dayOffset) % $offices->count()];
                $isLate = ($employeeIndex + $dayOffset) % 3 === 0;
                $isToday = $dayOffset === 0;
                $isActiveToday = $isToday && $employeeIndex === 0;
                $workStart = CarbonImmutable::parse($date->toDateString().' '.$office->work_start_time);
                $workEnd = CarbonImmutable::parse($date->toDateString().' '.$office->work_end_time);
                $inAt = $isLate
                    ? $workStart->addMinutes(11 + ($employeeIndex % 4) * 3)
                    : $workStart->subMinutes(5 + ($employeeIndex % 5));
                $outAt = $isActiveToday
                    ? null
                    : $workEnd->addMinutes(3 + ($employeeIndex % 6) * 5);

                Attendance::create([
                    'user_id' => $employee->id,
                    'office_id' => $office->id,
                    'date' => $date->toDateString(),
                    'in_at' => $inAt,
                    'out_at' => $outAt,
                    'in_latitude' => $this->offsetCoordinate((float) $office->latitude, $employeeIndex, $dayOffset),
                    'in_longitude' => $this->offsetCoordinate((float) $office->longitude, $dayOffset, $employeeIndex),
                    'proof_photo' => null,
                    'status' => $isLate ? AttendanceStatus::Late : AttendanceStatus::OnTime,
                    'created_by' => 'demo-seeder',
                    'updated_by' => 'demo-seeder',
                ]);
            }
        }
    }

    private function offsetCoordinate(float $coordinate, int $firstIndex, int $secondIndex): string
    {
        $offset = (($firstIndex % 4) - 1.5) * 0.000045 + (($secondIndex % 3) - 1) * 0.00002;

        return number_format($coordinate + $offset, 8, '.', '');
    }
}
