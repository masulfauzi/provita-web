<?php

use App\Http\Modules\Kas\Controllers\KasController;
use App\Http\Modules\Transaksi\Models\Transaksi;
use Illuminate\Support\Facades\Route;

Route::prefix('kas')->name('kas.')->group(function () {
    foreach (['penerimaan' => Transaksi::KAS_MASUK, 'pengeluaran' => Transaksi::KAS_KELUAR] as $menu => $jenis) {
        Route::get("/{$menu}", [KasController::class, 'index'])->defaults('jenis', $jenis)->name($menu);
        Route::post("/{$menu}", [KasController::class, 'store'])->defaults('jenis', $jenis)->name("{$menu}.store");
    }

    Route::get('/{kas}', [KasController::class, 'show'])->middleware('can:kas.lihat')->name('show');
    Route::post('/{kas}/batal', [KasController::class, 'batal'])->middleware('can:kas.batal')->name('batal');
});
