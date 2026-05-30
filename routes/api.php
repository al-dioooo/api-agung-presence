<?php

use App\Http\Controllers\Api\AttendanceController;
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
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('role:administrator')
            ->name('auth.register');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('offices', OfficeController::class);
    Route::post('attendances/{attendance}/checkout', [AttendanceController::class, 'checkout'])->name('attendances.checkout');
    Route::apiResource('attendances', AttendanceController::class);
    Route::middleware('role:administrator')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
