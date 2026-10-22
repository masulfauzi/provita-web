<?php

use App\Http\Modules\Nasabah\Controllers\NasabahController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:nasabah.lihat')->group(function () {
    Route::resource('nasabah', NasabahController::class)
        ->parameters(['nasabah' => 'nasabah']);

    Route::patch('/nasabah/{nasabah}/aktif', [NasabahController::class, 'toggleActive'])
        ->name('nasabah.toggle-active');
});
