<?php

namespace App\Llm;

class ScoreResult
{
    public const RATINGS = ['Strong', 'Moderate', 'Weak'];

    /**
     * @param  array<int, string>  $reasons
     * @param  array<int, string>  $flags
     */
    public function __construct(
        public readonly string $rating,
        public readonly array $reasons,
        public readonly array $flags,
        public readonly ?string $commentary,
        public readonly string $model,
        public readonly int $durationMs,
        public readonly int $promptChars,
    ) {}
}
