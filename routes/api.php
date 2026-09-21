<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;

// ==========================================
// 🔓 PUBLIC ROUTE (Bisa diakses siapa saja TANPA login)
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Route Webhook Notification dari Midtrans
Route::post('/midtrans/notification', [PaymentController::class, 'callback']);

// Siapa pun boleh melihat daftar barang dan kategori tanpa harus punya akun/login
Route::get('/equipments', [EquipmentController::class, 'index']);
Route::get('/equipments/{id}', [EquipmentController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);


// ==========================================
// 🔒 PROTECTED ROUTE (Wajib Bawa Token Sanctum / Harus Login)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // Route::('/me', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Route::post('/users/{id}', [UserController::class, 'update']);

    Route::apiResource('users', UserController::class);

    // CRUD Equipment & Category untuk ADMIN (tambah, edit, hapus barang) tetap di dalam sini
    Route::post('/equipments', [EquipmentController::class, 'store']);
    Route::put('/equipments/{id}', [EquipmentController::class, 'update']);
    Route::delete('/equipments/{id}', [EquipmentController::class, 'destroy']);

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Route khusus upload payment & update status rental
    Route::post('/rentals/{id}/payment', [RentalController::class, 'uploadPayment']);
    Route::put('/rentals/{id}/status', [RentalController::class, 'updateStatus']);

    Route::apiResource('rentals', RentalController::class)->only(['index', 'store', 'show']);
    Route::apiResource('payments', PaymentController::class)->only(['store', 'show']);
});
