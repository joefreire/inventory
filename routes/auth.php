<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Only login: single-owner inventory, user created by `php artisan inventario:usuario`.
// No sign-up and no password reset by e-mail (the app sends no e-mail).
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
