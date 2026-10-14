<?php

declare(strict_types=1);

namespace App\Llm;

/**
 * Spec section 8 "Run behavior": "On rate-limit error: mark run
 * paused_rate_limited, record reset time if given, stop cleanly."
 * Distinguished from a generic LlmDriverException so the run
 * orchestrator can tell the two apart.
 */
final class LlmRateLimitedException extends LlmDriverException
{
    public function __construct(string $message, public readonly ?\DateTimeInterface $resetAt = null)
    {
        parent::__construct($message);
    }
}
