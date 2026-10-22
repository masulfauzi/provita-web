<?php

use App\Http\Modules\Transaksi\Controllers\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::prefix('transaksi')->name('transaksi.')->group(function () {
    Route::get('/', [TransaksiController::class, 'index'])->middleware('can:transaksi.setor')->name('index');
    Route::post('/setor', [TransaksiController::class, 'setor'])->middleware('can:transaksi.setor')->name('setor');
    Route::post('/tarik', [TransaksiController::class, 'tarik'])->middleware('can:transaksi.tarik')->name('tarik');
    Route::post('/{transaksi}/balik', [TransaksiController::class, 'balik'])->middleware('can:transaksi.balik')->name('balik');
});
