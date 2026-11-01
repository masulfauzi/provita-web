<?php

use App\Http\Modules\Autodebet\Controllers\AutodebetController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:autodebet.lihat')->prefix('autodebet')->name('autodebet.')->group(function () {
    Route::get('/', [AutodebetController::class, 'index'])->name('index');
    Route::get('/{autodebet}', [AutodebetController::class, 'show'])->name('show');

    Route::middleware('can:autodebet.kelola')->group(function () {
        Route::post('/', [AutodebetController::class, 'store'])->name('store');
        Route::put('/{autodebet}', [AutodebetController::class, 'update'])->name('update');
        Route::post('/{autodebet}/hentikan', [AutodebetController::class, 'hentikan'])->name('hentikan');
        Route::post('/{autodebet}/lanjutkan', [AutodebetController::class, 'lanjutkan'])->name('lanjutkan');
    });
});
