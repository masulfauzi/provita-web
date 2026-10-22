<?php

use App\Http\Controllers\Auth\TokenController;
use Illuminate\Support\Facades\Route;

// Prefix api/v1 diatur di bootstrap/app.php. Route API modul ada di
// app/Http/Modules/*/routes/api.php.

Route::post('/auth/token', [TokenController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.auth.token');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/auth/token', [TokenController::class, 'destroy'])->name('api.auth.token.destroy');
    Route::get('/auth/user', fn () => request()->user()->only('id', 'username', 'name'))->name('api.auth.user');
});
