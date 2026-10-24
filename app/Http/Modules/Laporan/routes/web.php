<?php

use App\Http\Modules\Laporan\Controllers\LaporanController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:laporan.keuangan')->prefix('laporan')->name('laporan.')->group(function () {
    $laporan = implode('|', array_keys(LaporanController::LAPORAN));

    Route::get('/', [LaporanController::class, 'index'])->name('index');
    Route::get('/{laporan}', [LaporanController::class, 'show'])->where('laporan', $laporan)->name('show');
    Route::get('/{laporan}/excel', [LaporanController::class, 'excel'])->where('laporan', $laporan)->name('excel');
});
