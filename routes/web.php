<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|up).*$');
