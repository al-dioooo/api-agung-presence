<?php

use App\Support\AttendanceAbsenceMaterializer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendances:materialize-absences
    {--date= : Materialize one date in Y-m-d format}
    {--start-date= : Materialize from this Y-m-d date}
    {--end-date= : Materialize through this Y-m-d date}
    {--user-id= : Limit materialization to one employee user ID}', function () {
    $result = app(AttendanceAbsenceMaterializer::class)->materialize(
        date: $this->option('date') ?: null,
        startDate: $this->option('start-date') ?: null,
        endDate: $this->option('end-date') ?: null,
        userId: $this->option('user-id') ? (int) $this->option('user-id') : null,
    );

    $this->info("Created: {$result['created']}; Updated: {$result['updated']}; Skipped: {$result['skipped']}");
})->purpose('Materialize absent attendance rows for employees without workday attendance');

Schedule::command('attendances:materialize-absences')
    ->dailyAt('23:59')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
