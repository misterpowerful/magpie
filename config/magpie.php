<?php

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    'llm' => [
        // 'cli' (Claude Code headless on the owner's plan) now; 'api' later.
        'driver' => env('MAGPIE_LLM_DRIVER', 'cli'),
        'scoring_model' => env('MAGPIE_LLM_SCORING_MODEL', 'claude-sonnet-5'),
        'triage_model' => env('MAGPIE_LLM_TRIAGE_MODEL', 'claude-haiku-4-5-20251001'),
        'cli_timeout' => env('MAGPIE_LLM_CLI_TIMEOUT', 120),
    ],

    'mail' => [
        // Gmail IMAP with an app password (spec section 2 "Mail intake").
        // The inbox_label must have "Show in IMAP" enabled in Gmail settings.
        'host' => env('MAGPIE_MAIL_HOST', 'imap.gmail.com'),
        'port' => env('MAGPIE_MAIL_PORT', 993),
        'encryption' => env('MAGPIE_MAIL_ENCRYPTION', 'ssl'),
        'username' => env('MAGPIE_MAIL_USERNAME'),
        'password' => env('MAGPIE_MAIL_PASSWORD'),
        'inbox_label' => env('MAGPIE_MAIL_INBOX_LABEL', 'jobs'),
        'processed_label' => env('MAGPIE_MAIL_PROCESSED_LABEL', 'jobs-processed'),
    ],
];
