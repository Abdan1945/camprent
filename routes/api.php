<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;

// Public Route
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected Route (Harus Bawa Token Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('equipments', EquipmentController::class);
    Route::apiResource('categories', CategoryController::class);

    // Route khusus upload payment & update status rental
    Route::post('/rentals/{id}/payment', [RentalController::class, 'uploadPayment']);
    Route::put('/rentals/{id}/status', [RentalController::class, 'updateStatus']); // Tambahan rute status admin

    Route::apiResource('rentals', RentalController::class)->only(['index', 'store', 'show']);

    Route::apiResource('payments', PaymentController::class)->only(['store', 'show']);
});
