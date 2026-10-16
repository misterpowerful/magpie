<?php

declare(strict_types=1);

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    'llm' => [
        // 'cli' (Claude Code headless on the owner's plan) now; 'api' later.
        'driver' => env('MAGPIE_LLM_DRIVER', 'cli'),
        'scoring_model' => env('MAGPIE_LLM_SCORING_MODEL', 'claude-sonnet-5-5'),
        'triage_model' => env('MAGPIE_LLM_TRIAGE_MODEL', 'claude-haiku-5-5'),
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

        // Application-confirmation scan (spec section 9's "deterministic
        // confirmation matcher" alternative to Gatsby): a second, separate
        // poll target from the job-alert inbox_label above — confirmation
        // emails ("thank you for applying") land in the owner's normal
        // mail, not the jobs label. Messages are only ever peeked at
        // (never marked \Seen, moved, or relabeled) so he can still see
        // them himself; confirmation_lookback_days is a rolling window
        // re-scanned every run, with ProcessedConfirmationEmail (by
        // Message-ID) as the dedup record since there's no IMAP-side
        // "already handled" signal to rely on.
        'confirmation_folder' => env('MAGPIE_MAIL_CONFIRMATION_FOLDER', 'INBOX'),
        // Kept short (vs. e.g. the email intake's unbounded "jobs" label
        // fetch) since this scan runs every 4 hours, not once a sweep —
        // there's no need to re-walk weeks of INBOX history each time.
        'confirmation_lookback_days' => env('MAGPIE_MAIL_CONFIRMATION_LOOKBACK_DAYS', 1),
        // Shared between the full pipeline run's confirmation_check stage
        // and the standalone magpie:check-confirmations command so the
        // two can never process the same inbox scan concurrently.
        'confirmation_lock_seconds' => env('MAGPIE_MAIL_CONFIRMATION_LOCK_SECONDS', 300),
    ],

    'sites' => [
        // The Add Site "Check URL" fetch. Separate from the enrichment
        // timeout so tuning one never silently changes the other.
        'probe_timeout' => env('MAGPIE_SITE_PROBE_TIMEOUT', 15),
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
