<?php

use App\Http\Modules\Rekening\Controllers\RekeningController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:rekening.lihat')->prefix('rekening')->name('rekening.')->group(function () {
    Route::get('/', [RekeningController::class, 'index'])->name('index');
    Route::get('/cari', [RekeningController::class, 'cari'])->name('cari');
    Route::get('/create', [RekeningController::class, 'create'])->middleware('can:rekening.buka')->name('create');
    Route::post('/', [RekeningController::class, 'store'])->middleware('can:rekening.buka')->name('store');
    Route::get('/{rekening}', [RekeningController::class, 'show'])->name('show');
    Route::post('/{rekening}/tutup', [RekeningController::class, 'tutup'])->middleware('can:rekening.tutup')->name('tutup');
});
