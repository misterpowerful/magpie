<?php

namespace App\Providers;

use App\Contracts\LlmDriver;
use App\Inbox\EmailParserRegistry;
use App\Inbox\HaikuFallbackParser;
use App\Inbox\ImapMailSource;
use App\Inbox\MailSource;
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

        $this->app->bind(MailSource::class, ImapMailSource::class);

        // No per-sender parsers yet — building one against guessed HTML
        // for LinkedIn/Indeed would be worse than not having it (see
        // Ticket 6 notes). Everything falls through to Haiku until a
        // real sample email is available to build an exact parser from.
        $this->app->singleton(EmailParserRegistry::class, function ($app) {
            return (new EmailParserRegistry)->register($app->make(HaikuFallbackParser::class));
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
