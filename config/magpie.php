<?php

declare(strict_types=1);

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

    'enrichment' => [
        // Spec section 5 "enrichment escalation": plain HTTP fetch of the
        // posting, falling back to needs_browser on a login wall or an
        // empty JS-rendered shell. min_body_length is the stripped-text
        // length below which a 200 response is treated as a JS shell.
        'timeout' => env('MAGPIE_ENRICHMENT_TIMEOUT', 15),
        'min_body_length' => env('MAGPIE_ENRICHMENT_MIN_BODY_LENGTH', 200),
    ],

    'run' => [
        // Spec section 8 "Run behavior": a global wall-clock cap for the
        // whole run, plus a smaller per-stage cap so one slow stage
        // (e.g. a hung intake fetch) can't eat the entire budget. Both
        // are checked per-item inside each stage's loop.
        'time_cap_minutes' => env('MAGPIE_RUN_TIME_CAP_MINUTES', 120),
        'stage_timeout_minutes' => env('MAGPIE_RUN_STAGE_TIMEOUT_MINUTES', 30),
        // Pooled intake fetch timeout per site (spec section 8 "per-site
        // stage timeout"), and the run lock's own safety-valve TTL (a bit
        // over the time cap, in case the process dies mid-run).
        'intake_timeout_seconds' => env('MAGPIE_RUN_INTAKE_TIMEOUT_SECONDS', 15),
        'lock_seconds' => env('MAGPIE_RUN_LOCK_SECONDS', 7500),
    ],
];
