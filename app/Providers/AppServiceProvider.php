<?php

namespace App\Providers;

use App\Contracts\LlmDriver;
use App\Llm\CliLlmDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmDriver::class, match (config('magpie.llm.driver')) {
            'cli' => CliLlmDriver::class,
            default => CliLlmDriver::class,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
