<?php

use App\Http\Modules\Nasabah\Controllers\DaftarSiswaController;
use App\Http\Modules\Nasabah\Controllers\ImporNasabahController;
use App\Http\Modules\Nasabah\Controllers\NasabahController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:nasabah.lihat')->group(function () {
    // Didaftarkan sebelum resource agar tidak tertangkap sebagai /nasabah/{nasabah}.
    Route::get('/nasabah/cari', [NasabahController::class, 'cari'])->name('nasabah.cari');
    Route::middleware('can:nasabah.impor')->group(function () {
        Route::get('/nasabah/impor', [ImporNasabahController::class, 'index'])->name('nasabah.impor');
        Route::post('/nasabah/impor/periksa', [ImporNasabahController::class, 'periksa'])->name('nasabah.impor.periksa');
        Route::post('/nasabah/impor', [ImporNasabahController::class, 'store'])->name('nasabah.impor.store');
    });
    Route::get('/nasabah/periksa-nisn', [NasabahController::class, 'periksaNisn'])
        ->middleware(['can:nasabah.tambah', 'throttle:30,1'])
        ->name('nasabah.periksa-nisn');

    Route::resource('nasabah', NasabahController::class)
        ->parameters(['nasabah' => 'nasabah']);

    Route::patch('/nasabah/{nasabah}/aktif', [NasabahController::class, 'toggleActive'])
        ->name('nasabah.toggle-active');
});

// Pencarian data siswa di sistem sekolah; terbuka untuk semua peran.
Route::get('/daftar-siswa', [DaftarSiswaController::class, 'index'])
    ->middleware(['can:siswa_sekolah.lihat', 'throttle:30,1'])
    ->name('daftar-siswa.index');
