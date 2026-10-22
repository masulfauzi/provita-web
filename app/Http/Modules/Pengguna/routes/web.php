<?php

use App\Http\Modules\Pengguna\Controllers\PenggunaController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:pengguna.lihat')->group(function () {
    Route::resource('pengguna', PenggunaController::class)
        ->except(['show', 'destroy'])
        ->parameters(['pengguna' => 'pengguna']);

    Route::put('/pengguna/{pengguna}/password', [PenggunaController::class, 'resetPassword'])
        ->name('pengguna.reset-password');
    Route::patch('/pengguna/{pengguna}/aktif', [PenggunaController::class, 'toggleActive'])
        ->name('pengguna.toggle-active');
});
