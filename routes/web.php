<?php

declare(strict_types=1);

use App\Http\Controllers\Actions\Auth\LoginAction;
use App\Http\Controllers\Actions\Auth\LogoutAction;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginAction::class)->name('login');
Route::post('/logout', LogoutAction::class);

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|sanctum|up).*$');
