<?php

declare(strict_types=1);

use App\Llm\CliLlmDriver;
use App\Llm\LlmDriverException;
use App\Llm\LlmRateLimitedException;
use App\Models\Listing;
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

test('a score response wrapped in a ```json code fence still parses correctly', function () {
    fakeScoreProcess("```json\n".json_encode(['rating' => 'Strong', 'reasons' => ['Good fit'], 'flags' => [], 'commentary' => 'Great match.'])."\n```");

    $result = (new CliLlmDriver)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Strong');
    expect($result->commentary)->toBe('Great match.');
});

test('a score response wrapped in a bare ``` code fence (no "json" label) still parses correctly', function () {
    fakeScoreProcess("```\n".json_encode(['rating' => 'Weak', 'reasons' => ['Stack mismatch'], 'flags' => []])."\n```");

    $result = (new CliLlmDriver)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Weak');
});

test('an unwrapped score response (no code fence) still parses correctly', function () {
    fakeScoreProcess(json_encode(['rating' => 'Moderate', 'reasons' => ['Partial fit'], 'flags' => []]));

    $result = (new CliLlmDriver)->score(Listing::factory()->make(), Preference::factory()->make());

    expect($result->rating)->toBe('Moderate');
});

test('genuinely invalid score JSON still throws, fence or no fence', function () {
    fakeScoreProcess('```json'."\n".'not actually json'."\n".'```');

    (new CliLlmDriver)->score(Listing::factory()->make(), Preference::factory()->make());
})->throws(LlmDriverException::class);
