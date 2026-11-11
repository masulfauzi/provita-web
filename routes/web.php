<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profil/password', [ProfileController::class, 'editPassword'])->name('profil.password');
    Route::put('/profil/password', [ProfileController::class, 'updatePassword']);
});

// CSRF cookie Sanctum juga di bawah api/v1: frontend provita-ib memakai VITE_API_URL=…/api/v1
// untuk semua permintaan, termasuk csrf-cookie.
Route::get('/api/v1/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->name('ib.csrf-cookie');
