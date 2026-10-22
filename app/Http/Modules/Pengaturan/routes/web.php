<?php

use App\Http\Modules\Pengaturan\Controllers\PengaturanController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:pengaturan.lihat')->group(function () {
    Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
    Route::put('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
});
