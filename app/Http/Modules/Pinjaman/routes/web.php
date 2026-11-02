<?php

use App\Http\Modules\Pinjaman\Controllers\PinjamanController;
use Illuminate\Support\Facades\Route;

Route::prefix('pinjaman')->name('pinjaman.')->group(function () {
    Route::middleware('can:pinjaman.lihat')->group(function () {
        Route::get('/', [PinjamanController::class, 'index'])->name('index');
        Route::get('/cari-nasabah', [PinjamanController::class, 'cariNasabah'])->middleware('can:pinjaman.akad')->name('cari-nasabah');
        Route::get('/create', [PinjamanController::class, 'create'])->middleware('can:pinjaman.akad')->name('create');
        Route::get('/{pinjaman}', [PinjamanController::class, 'show'])->name('show');
    });

    Route::post('/', [PinjamanController::class, 'store'])->middleware('can:pinjaman.akad')->name('store');
    Route::post('/{pinjaman}/bayar', [PinjamanController::class, 'bayar'])->middleware('can:pinjaman.angsuran')->name('bayar');
    Route::post('/{pinjaman}/batal-bayar', [PinjamanController::class, 'batalBayar'])->middleware('can:pinjaman.batal')->name('batal-bayar');
    Route::post('/{pinjaman}/batal', [PinjamanController::class, 'batal'])->middleware('can:pinjaman.batal')->name('batal');
});
