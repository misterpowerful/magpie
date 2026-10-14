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
];
