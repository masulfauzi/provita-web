<?php

use App\Http\Modules\Pinjaman\Controllers\PinjamanController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:pinjaman.lihat')->group(function () {
    Route::get('/pinjaman', [PinjamanController::class, 'index'])->name('pinjaman.index');
});
