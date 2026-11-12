<?php

use App\Http\Modules\IbNasabah\Controllers\AktivasiNasabahController;
use App\Http\Modules\IbNasabah\Controllers\AuthNasabahController;
use App\Http\Modules\IbNasabah\Controllers\PasswordNasabahController;
use App\Http\Modules\IbNasabah\Controllers\ProfilNasabahController;
use Illuminate\Support\Facades\Route;

// Prefix api/v1/nasabah, nama ib.*, middleware api (lihat ModuleServiceProvider).
// Batas gagal login/aktivasi diatur di controller (per No. HP / identitas + IP).
Route::middleware('ib.sesi')->group(function () {
    Route::post('/aktivasi/cek', [AktivasiNasabahController::class, 'cek'])->name('aktivasi.cek');
    Route::post('/aktivasi', [AktivasiNasabahController::class, 'simpan'])->middleware('throttle:ib-password')->name('aktivasi');
    Route::post('/login', [AuthNasabahController::class, 'login'])->name('login');
});

Route::middleware(['auth.nasabah', 'throttle:ib'])->group(function () {
    Route::get('/me', [AuthNasabahController::class, 'me'])->name('me');
    Route::post('/logout', [AuthNasabahController::class, 'logout'])->name('logout');
    Route::put('/password', [PasswordNasabahController::class, 'update'])->middleware('throttle:ib-password')->name('password');
    Route::put('/no-hp', [ProfilNasabahController::class, 'updateNoHp'])->middleware('throttle:ib-password')->name('no-hp');
    Route::get('/profil', [ProfilNasabahController::class, 'show'])->name('profil');
    Route::put('/profil', [ProfilNasabahController::class, 'update'])->name('profil.update');
});
