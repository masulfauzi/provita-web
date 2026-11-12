<?php

use App\Http\Modules\Rekening\Controllers\Ib\RekeningNasabahController;
use Illuminate\Support\Facades\Route;

// Internet Banking (prefix api/v1/nasabah, nama ib.*): hanya rekening milik nasabah yang login.
Route::middleware(['auth.nasabah', 'throttle:ib'])->prefix('rekening')->name('rekening.')->group(function () {
    Route::get('/', [RekeningNasabahController::class, 'index'])->name('index');
    Route::get('/{id}', [RekeningNasabahController::class, 'show'])->whereUuid('id')->name('show');
    Route::get('/{id}/mutasi', [RekeningNasabahController::class, 'mutasi'])->whereUuid('id')->name('mutasi');
    Route::get('/{id}/mutasi.pdf', [RekeningNasabahController::class, 'pdf'])->whereUuid('id')->middleware('throttle:ib-pdf')->name('mutasi.pdf');
});
