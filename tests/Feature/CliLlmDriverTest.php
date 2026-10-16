<?php

declare(strict_types=1);

use App\Llm\CliLlmDriver;
use App\Llm\LlmDriverException;
use App\Llm\LlmRateLimitedException;
use App\Models\Listing;
use App\Models\LlmCall;
use App\Models\Preference;
use Illuminate\Support\Facades\Process;

function fakeScoreProcess(string $resultText): void
{
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => false, 'result' => $resultText]),
            exitCode: 0,
        ),
    ]);
}

test('a rate-limit error envelope throws LlmRateLimitedException, not the generic exception', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => true, 'result' => 'Error: rate limit exceeded, please retry later']),
            exitCode: 0,
        ),
    ]);

    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmRateLimitedException::class);

test('a non-rate-limit error envelope throws the generic LlmDriverException', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => true, 'result' => 'Something else went wrong']),
            exitCode: 0,
        ),
    ]);

    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmDriverException::class);

test('a rate-limited process failure (non-zero exit) also throws LlmRateLimitedException', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'HTTP 429 Too Many Requests', exitCode: 1),
    ]);

    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');
})->throws(LlmRateLimitedException::class);

test('a successful completion returns the raw result text', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['is_error' => false, 'result' => 'the answer']),
            exitCode: 0,
        ),
    ]);

    expect(app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001'))->toBe('the answer');
});

test('a successful call persists an LlmCall row with its token usage and cost', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'is_error' => false,
                'result' => 'the answer',
                'total_cost_usd' => 0.0123,
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 37,
                    'cache_creation_input_tokens' => 500,
                    'cache_read_input_tokens' => 0,
                ],
            ]),
            exitCode: 0,
        ),
    ]);

    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');

    expect(LlmCall::count())->toBe(1);

    $call = LlmCall::first();
    expect($call->context)->toBe('generic');
    expect($call->model)->toBe('claude-haiku-4-5-20251001');
    expect($call->input_tokens)->toBe(10);
    expect($call->output_tokens)->toBe(37);
    expect((float) $call->cost_usd)->toBe(0.0123);
    expect($call->outcome)->toBe('ok');
});

test('a failed call still persists an LlmCall row, so failures show up in usage stats too', function () {
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'boom', exitCode: 1),
    ]);

    try {
        app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');
    } catch (LlmDriverException) {
        // expected
    }

    expect(LlmCall::count())->toBe(1);
    expect(LlmCall::first()->outcome)->toBe('process_failed');
});

test('a score response wrapped in a ```json code fence still parses correctly', function () {
    fakeScoreProcess("```json\n".json_encode(['rating' => 'Strong', 'reasons' => ['Good fit'], 'flags' => [], 'commentary' => 'Great match.'])."\n```");

    $result = app(CliLlmDriver::class)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Strong');
    expect($result->commentary)->toBe('Great match.');
});

test('a score response wrapped in a bare ``` code fence (no "json" label) still parses correctly', function () {
    fakeScoreProcess("```\n".json_encode(['rating' => 'Weak', 'reasons' => ['Stack mismatch'], 'flags' => []])."\n```");

    $result = app(CliLlmDriver::class)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Weak');
});

test('an unwrapped score response (no code fence) still parses correctly', function () {
    fakeScoreProcess(json_encode(['rating' => 'Moderate', 'reasons' => ['Partial fit'], 'flags' => []]));

    $result = app(CliLlmDriver::class)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Moderate');
});

test('genuinely invalid score JSON still throws, fence or no fence', function () {
    fakeScoreProcess('```json'."\n".'not actually json'."\n".'```');

    app(CliLlmDriver::class)->score(Listing::factory()->make(), Preference::factory()->make());
})->throws(LlmDriverException::class);

test('a call made during a pipeline run is tagged with that run', function () {
    Process::fake([
        '*' => Process::result(output: json_encode(['is_error' => false, 'result' => 'ok', 'total_cost_usd' => 0.01]), exitCode: 0),
    ]);

    $run = \App\Models\Run::factory()->create();
    \Illuminate\Support\Facades\Context::add('run_id', $run->id);

    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');

    \Illuminate\Support\Facades\Context::forget('run_id');
    app(CliLlmDriver::class)->complete('hello', 'claude-haiku-4-5-20251001');

    expect(LlmCall::orderBy('id')->pluck('run_id')->all())->toBe([$run->id, null]);
});
