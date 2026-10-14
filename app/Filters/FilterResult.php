<?php

declare(strict_types=1);

namespace App\Filters;

/**
 * Every filter stage returns one of these three verdicts. Regex filters
 * only ever return FAIL when confident; everything else is UNKNOWN and
 * continues to the next stage — nothing is silently dropped or approved
 * (spec section 6).
 */
final readonly class FilterResult
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $verdict,
        public array $flags = [],
        public ?string $reason = null,
        public array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public static function pass(array $flags = [], array $data = []): self
    {
        return new self(self::PASS, $flags, null, $data);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public static function fail(string $reason, array $flags = [], array $data = []): self
    {
        return new self(self::FAIL, $flags, $reason, $data);
    }

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public static function unknown(array $flags = [], array $data = []): self
    {
        return new self(self::UNKNOWN, $flags, null, $data);
    }

    public function isFail(): bool
    {
        return $this->verdict === self::FAIL;
    }

    public function isPass(): bool
    {
        return $this->verdict === self::PASS;
    }

    public function isUnknown(): bool
    {
        return $this->verdict === self::UNKNOWN;
    }
}
