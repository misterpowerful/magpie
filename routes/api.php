<?php

use App\Http\Controllers\Api\ListingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/listings', [ListingController::class, 'index']);
    Route::patch('/listings/{listing}/status', [ListingController::class, 'updateStatus']);
});
