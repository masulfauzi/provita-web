<?php

use App\Http\Modules\Akuntansi\Controllers\AkunController;
use App\Http\Modules\Akuntansi\Controllers\JurnalController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:akun.lihat')->group(function () {
    Route::resource('akun', AkunController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('/akun/{akun}/aktif', [AkunController::class, 'toggleActive'])->name('akun.toggle-active');
});

Route::middleware('can:jurnal.lihat')->group(function () {
    Route::resource('jurnal', JurnalController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('/jurnal/{jurnal}/balik', [JurnalController::class, 'balik'])->name('jurnal.balik');
});
