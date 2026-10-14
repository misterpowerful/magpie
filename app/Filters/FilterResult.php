<?php

namespace App\Filters;

/**
 * Every filter stage returns one of these three verdicts. Regex filters
 * only ever return FAIL when confident; everything else is UNKNOWN and
 * continues to the next stage — nothing is silently dropped or approved
 * (spec section 6).
 */
class FilterResult
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const UNKNOWN = 'unknown';

    /**
     * @param  array<string, mixed>  $flags
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $verdict,
        public readonly array $flags = [],
        public readonly ?string $reason = null,
        public readonly array $data = [],
    ) {}

    public static function pass(array $flags = [], array $data = []): self
    {
        return new self(self::PASS, $flags, null, $data);
    }

    public static function fail(string $reason, array $flags = [], array $data = []): self
    {
        return new self(self::FAIL, $flags, $reason, $data);
    }

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
