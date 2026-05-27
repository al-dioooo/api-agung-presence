<?php

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

describe('database seeder', function () {
    test('seeds demo employees with password credentials', function () {
        $this->seed(DatabaseSeeder::class);

        $employees = User::query()
            ->where('role', UserRole::Employee)
            ->where('email', 'like', '%@agungpresence.test')
            ->orderBy('username')
            ->get();

        expect($employees)->toHaveCount(10);

        foreach ($employees as $employee) {
            expect($employee->role)->toBe(UserRole::Employee)
                ->and(Hash::check('password', $employee->password))->toBeTrue()
                ->and($employee->email_verified_at)->not->toBeNull();
        }
    });

    test('seeds multiple Palembang offices with working times', function () {
        $this->seed(DatabaseSeeder::class);

        $offices = Office::query()
            ->where('address', 'like', '%Palembang%')
            ->orderBy('name')
            ->get();

        expect($offices)->toHaveCount(5);

        foreach ($offices as $office) {
            expect((float) $office->latitude)->toBeGreaterThan(-3.1)->toBeLessThan(-2.9)
                ->and((float) $office->longitude)->toBeGreaterThan(104.6)->toBeLessThan(104.9)
                ->and($office->work_start_time)->not->toBeNull()
                ->and($office->work_end_time)->not->toBeNull()
                ->and($office->is_active)->toBeTrue();
        }
    });

    test('seeds attendance history from one week ago through today', function () {
        $this->seed(DatabaseSeeder::class);

        $today = CarbonImmutable::today();
        $expectedDates = collect(range(7, 0))
            ->map(fn (int $offset) => $today->subDays($offset)->toDateString())
            ->values();

        $attendanceDates = Attendance::query()
            ->whereDate('date', '>=', $expectedDates->first())
            ->whereDate('date', '<=', $expectedDates->last())
            ->pluck('date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        expect($attendanceDates->all())->toBe($expectedDates->sort()->values()->all())
            ->and(Attendance::query()->whereDate('date', $today->toDateString())->whereNull('out_at')->count())->toBeGreaterThan(0)
            ->and(Attendance::query()->whereDate('date', $today->toDateString())->whereNotNull('out_at')->count())->toBeGreaterThan(0)
            ->and(Attendance::query()->where('status', AttendanceStatus::OnTime)->count())->toBeGreaterThan(0)
            ->and(Attendance::query()->where('status', AttendanceStatus::Late)->count())->toBeGreaterThan(0);
    });

    test('seeding is idempotent for demo data', function () {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        expect(User::query()->where('email', 'like', '%@agungpresence.test')->count())->toBe(10)
            ->and(Office::query()->where('address', 'like', '%Palembang%')->count())->toBe(5)
            ->and(Attendance::query()->where('created_by', 'demo-seeder')->count())->toBe(80);
    });
});
