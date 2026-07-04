<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\AttendanceRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'name' => config('app.name'),
    ]);
})->name('api.status');

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        Route::patch('/me', [AuthController::class, 'updateMe'])->name('auth.me.update');
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('role:administrator')
            ->name('auth.register');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('offices', OfficeController::class);
    Route::get('attendance-requests', [AttendanceRequestController::class, 'index'])
        ->name('attendance-requests.index');
    Route::post('attendance-requests', [AttendanceRequestController::class, 'store'])
        ->name('attendance-requests.store');
    Route::get('attendance-requests/{attendanceRequest}', [AttendanceRequestController::class, 'show'])
        ->name('attendance-requests.show');
    Route::patch('attendance-requests/{attendanceRequest}/review', [AttendanceRequestController::class, 'review'])
        ->middleware('role:administrator')
        ->name('attendance-requests.review');
    Route::get('attendances/summary', [AttendanceReportController::class, 'summary'])
        ->middleware('role:administrator')
        ->name('attendances.summary');
    Route::get('attendances/chart', [AttendanceReportController::class, 'chart'])
        ->name('attendances.chart');
    Route::get('attendances/export', [AttendanceReportController::class, 'export'])
        ->middleware('role:administrator')
        ->name('attendances.export');
    Route::post('attendances/manual', [AttendanceController::class, 'storeManual'])
        ->middleware('role:administrator')
        ->name('attendances.manual');
    Route::post('attendances/{attendance}/checkout', [AttendanceController::class, 'checkout'])->name('attendances.checkout');
    Route::apiResource('attendances', AttendanceController::class);
    Route::middleware('role:administrator')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
