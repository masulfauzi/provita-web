<?php

use App\Http\Modules\IbNasabah\Controllers\AuthNasabahController;
use App\Http\Modules\IbNasabah\Controllers\PasswordNasabahController;
use App\Http\Modules\IbNasabah\Controllers\ProfilNasabahController;
use Illuminate\Support\Facades\Route;

// Prefix api/v1/nasabah, nama ib.*, middleware api (lihat ModuleServiceProvider).
// Batas gagal login diatur di AuthNasabahController (per NIS + IP).
Route::post('/login', [AuthNasabahController::class, 'login'])->name('login');

Route::middleware(['auth.nasabah', 'throttle:ib'])->group(function () {
    Route::middleware('ib.onboarding:password,profil')->group(function () {
        Route::get('/me', [AuthNasabahController::class, 'me'])->name('me');
        Route::post('/logout', [AuthNasabahController::class, 'logout'])->name('logout');
        Route::put('/password', [PasswordNasabahController::class, 'update'])->middleware('throttle:ib-password')->name('password');
    });

    Route::middleware('ib.onboarding:profil')->group(function () {
        Route::get('/profil', [ProfilNasabahController::class, 'show'])->name('profil');
        Route::put('/profil', [ProfilNasabahController::class, 'update'])->name('profil.update');
    });
});
