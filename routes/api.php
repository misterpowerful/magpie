<?php

declare(strict_types=1);

use App\Http\Controllers\Actions\Auth\CurrentUserAction;
use App\Http\Controllers\Actions\Health\PingAction;
use App\Http\Controllers\Actions\Insights\CostInsightsAction;
use App\Http\Controllers\Actions\Insights\RatingInsightsAction;
use App\Http\Controllers\Actions\Insights\SourceInsightsAction;
use App\Http\Controllers\Actions\Listings\IndexListingsAction;
use App\Http\Controllers\Actions\Preferences\DownloadResumeAction;
use App\Http\Controllers\Actions\Preferences\RescoreStaleListingsAction;
use App\Http\Controllers\Actions\Preferences\ShowPreferencesAction;
use App\Http\Controllers\Actions\Preferences\UpdatePreferencesAction;
use App\Http\Controllers\Actions\Preferences\UploadResumeAction;
use App\Http\Controllers\Actions\Listings\UpdateListingStatusAction;
use App\Http\Controllers\Actions\Runs\IndexRunsAction;
use App\Http\Controllers\Actions\Sites\DestroySiteAction;
use App\Http\Controllers\Actions\Sites\IndexSitesAction;
use App\Http\Controllers\Actions\Sites\ProbeSiteAction;
use App\Http\Controllers\Actions\Sites\RunSiteAction;
use App\Http\Controllers\Actions\Sites\StoreSiteAction;
use App\Http\Controllers\Actions\Sites\UpdateSiteAction;
use App\Http\Controllers\Actions\Stats\RatingStatsAction;
use App\Http\Controllers\Actions\Stats\SourceStatsAction;
use App\Http\Controllers\Actions\Stats\UsageStatsAction;
use Illuminate\Support\Facades\Route;

Route::get('/user', CurrentUserAction::class)->middleware('auth:sanctum');

Route::get('/ping', PingAction::class)->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/listings', IndexListingsAction::class);
    Route::patch('/listings/{listing}/status', UpdateListingStatusAction::class);
    Route::get('/runs', IndexRunsAction::class);

    Route::prefix('preferences')->group(function () {
        Route::get('/', ShowPreferencesAction::class);
        Route::put('/', UpdatePreferencesAction::class);
        Route::post('/resume', UploadResumeAction::class);
        Route::get('/resume', DownloadResumeAction::class);
        Route::post('/rescore', RescoreStaleListingsAction::class);
    });

    Route::prefix('stats')->group(function () {
        Route::get('/sources', SourceStatsAction::class);
        Route::get('/ratings', RatingStatsAction::class);
        Route::get('/usage', UsageStatsAction::class);
    });

    Route::prefix('insights')->group(function () {
        Route::get('/cost', CostInsightsAction::class);
        Route::get('/sources', SourceInsightsAction::class);
        Route::get('/ratings', RatingInsightsAction::class);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/sites', IndexSitesAction::class);
        Route::post('/sites', StoreSiteAction::class);
        Route::post('/sites/probe', ProbeSiteAction::class);
        Route::put('/sites/{site}', UpdateSiteAction::class);
        Route::delete('/sites/{site}', DestroySiteAction::class);
        Route::post('/sites/{site}/run', RunSiteAction::class);
    });
});
