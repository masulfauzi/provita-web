<?php

use App\Http\Modules\Perangkat\Controllers\PerangkatController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:perangkat.lihat')->prefix('perangkat')->name('perangkat.')->group(function () {
    Route::get('/', [PerangkatController::class, 'index'])->name('index');

    Route::middleware('can:perangkat.kelola')->group(function () {
        Route::post('/', [PerangkatController::class, 'store'])->name('store');
        Route::put('/{perangkat}', [PerangkatController::class, 'update'])->name('update');
        Route::post('/{perangkat}/toggle', [PerangkatController::class, 'toggle'])->name('toggle');
        Route::delete('/{perangkat}', [PerangkatController::class, 'destroy'])->name('destroy');
    });
});
