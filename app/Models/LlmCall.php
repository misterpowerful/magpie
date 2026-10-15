<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per `claude` CLI invocation (CliLlmDriver::invoke). Persists
 * what used to only reach a text log file — exact token usage and
 * Anthropic's own computed cost for the call, straight from the CLI's
 * `--output-format json` envelope. The owner is on Claude Pro, not
 * metered API billing, so cost_usd is "what this would have cost on
 * the API" — a usage-trend proxy, not a real charge.
 */
#[Fillable([
    'context',
    'model',
    'input_tokens',
    'output_tokens',
    'cache_creation_input_tokens',
    'cache_read_input_tokens',
    'cost_usd',
    'duration_ms',
    'outcome',
    'created_at',
])]
class LlmCall extends Model
{
    public $timestamps = false;
}
