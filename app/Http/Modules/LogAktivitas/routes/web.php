<?php

use App\Http\Modules\LogAktivitas\Controllers\LogAktivitasController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:log_aktivitas.lihat')->group(function () {
    Route::get('/log-aktivitas', [LogAktivitasController::class, 'index'])->name('log-aktivitas.index');
});
