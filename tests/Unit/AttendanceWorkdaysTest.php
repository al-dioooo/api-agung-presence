<?php

use App\Support\AttendanceWorkdays;

test('workday count includes the same Monday through Saturday date', function () {
    expect(AttendanceWorkdays::count('2026-06-06', '2026-06-06'))->toBe(1);
});

test('workday count skips Sunday in an inclusive range', function () {
    expect(AttendanceWorkdays::count('2026-06-05', '2026-06-08'))->toBe(3);
});

test('workday count returns zero for a Sunday only range', function () {
    expect(AttendanceWorkdays::count('2026-06-07', '2026-06-07'))->toBe(0);
});

test('workday count returns zero for a reversed range', function () {
    expect(AttendanceWorkdays::count('2026-06-08', '2026-06-05'))->toBe(0);
});

test('workday dates return only counted days', function () {
    expect(collect(AttendanceWorkdays::dates('2026-06-05', '2026-06-08'))
        ->map->toDateString()
        ->all())->toBe([
            '2026-06-05',
            '2026-06-06',
            '2026-06-08',
        ]);
});
