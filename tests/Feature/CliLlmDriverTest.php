<?php

declare(strict_types=1);

use App\Llm\CliLlmDriver;
use App\Llm\LlmDriverException;
use App\Llm\LlmRateLimitedException;
use Illuminate\Support\Facades\Process;

test('a rate-limit error envelope throws LlmRateLimitedException, not the generic exception', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => true, 'result' => 'Error: rate limit exceeded, please retry later']),
            exitCode: 0,
        ),
    ]);

    (new CliLlmDriver)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmRateLimitedException::class);

test('a non-rate-limit error envelope throws the generic LlmDriverException', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => true, 'result' => 'Something else went wrong']),
            exitCode: 0,
        ),
    ]);

    (new CliLlmDriver)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmDriverException::class);

test('a rate-limited process failure (non-zero exit) also throws LlmRateLimitedException', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'HTTP 429 Too Many Requests', exitCode: 1),
    ]);

    (new CliLlmDriver)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmRateLimitedException::class);

test('a successful completion returns the raw result text', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => false, 'result' => 'the answer']),
            exitCode: 0,
        ),
    ]);

    expect((new CliLlmDriver)->complete('hello', 'claude-haiku-4-5-20251001'))->toBe('the answer');
});
