<?php

use App\Http\Modules\Laporan\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

Route::prefix('laporan')->name('laporan.')->group(function () {
    Route::get('/', [LaporanController::class, 'index'])->middleware('can:laporan.keuangan')->name('index');
    Route::get('/neraca', [LaporanController::class, 'neraca'])->middleware('can:laporan.neraca')->name('neraca');
});
