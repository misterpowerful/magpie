<?php

declare(strict_types=1);

use App\Http\Controllers\Actions\Auth\CurrentUserAction;
use App\Http\Controllers\Actions\Health\PingAction;
use App\Http\Controllers\Actions\Listings\IndexListingsAction;
use App\Http\Controllers\Actions\Listings\UpdateListingStatusAction;
use App\Http\Controllers\Actions\Sites\DestroySiteAction;
use App\Http\Controllers\Actions\Sites\IndexSitesAction;
use App\Http\Controllers\Actions\Sites\RunSiteAction;
use App\Http\Controllers\Actions\Sites\StoreSiteAction;
use App\Http\Controllers\Actions\Sites\UpdateSiteAction;
use Illuminate\Support\Facades\Route;

Route::get('/user', CurrentUserAction::class)->middleware('auth:sanctum');

Route::get('/ping', PingAction::class)->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/listings', IndexListingsAction::class);
    Route::patch('/listings/{listing}/status', UpdateListingStatusAction::class);

    Route::prefix('admin')->group(function () {
        Route::get('/sites', IndexSitesAction::class);
        Route::post('/sites', StoreSiteAction::class);
        Route::put('/sites/{site}', UpdateSiteAction::class);
        Route::delete('/sites/{site}', DestroySiteAction::class);
        Route::post('/sites/{site}/run', RunSiteAction::class);
    });
});
