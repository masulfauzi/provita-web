<?php

use App\Http\Modules\Nasabah\Controllers\NasabahController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:nasabah.lihat')->group(function () {
    // Didaftarkan sebelum resource agar tidak tertangkap sebagai /nasabah/{nasabah}.
    Route::get('/nasabah/cari', [NasabahController::class, 'cari'])->name('nasabah.cari');

    Route::resource('nasabah', NasabahController::class)
        ->parameters(['nasabah' => 'nasabah']);

    Route::patch('/nasabah/{nasabah}/aktif', [NasabahController::class, 'toggleActive'])
        ->name('nasabah.toggle-active');
});
