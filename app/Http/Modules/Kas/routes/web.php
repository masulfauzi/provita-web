<?php

use App\Http\Modules\Kas\Controllers\KasController;
use App\Http\Modules\Kas\Models\Kas;
use Illuminate\Support\Facades\Route;

Route::prefix('kas')->name('kas.')->group(function () {
    foreach (['penerimaan' => Kas::MASUK, 'pengeluaran' => Kas::KELUAR] as $menu => $jenis) {
        Route::get("/{$menu}", [KasController::class, 'index'])->defaults('jenis', $jenis)->name($menu);
        Route::post("/{$menu}", [KasController::class, 'store'])->defaults('jenis', $jenis)->name("{$menu}.store");
    }

    Route::middleware('can:kas.lihat')->group(function () {
        Route::get('/{kas}', [KasController::class, 'show'])->name('show');
        Route::post('/{kas}/batal', [KasController::class, 'batal'])->name('batal');
    });
});
