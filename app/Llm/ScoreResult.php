<?php

declare(strict_types=1);

namespace App\Llm;

final readonly class ScoreResult
{
    public const RATINGS = ['Strong', 'Moderate', 'Weak'];

    /**
     * @param  array<int, string>  $reasons
     * @param  array<int, string>  $flags
     */
    public function __construct(
        public string $rating,
        public array $reasons,
        public array $flags,
        public ?string $commentary,
        public string $model,
        public int $durationMs,
        public int $promptChars,
    ) {}
}
