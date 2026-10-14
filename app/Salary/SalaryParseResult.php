<?php

declare(strict_types=1);

namespace App\Salary;

final readonly class SalaryParseResult
{
    /**
     * @param  array<int, array{low: ?float, high: ?float, period: string, type: string, label: ?string}>  $tiers
     * @param  array<string, bool>  $flags
     */
    public function __construct(
        public array $tiers,
        public ?string $snippet,
        public string $confidence,
        public array $flags,
    ) {}

    /**
     * Base-salary tiers only (excludes OTE-only figures), which is what
     * pass/fail and ranking decisions are made against.
     *
     * @return array<int, array{low: ?float, high: ?float, period: string, type: string, label: ?string}>
     */
    public function baseTiers(): array
    {
        return array_values(array_filter($this->tiers, fn ($t) => $t['type'] === 'base'));
    }

    /**
     * The top of the highest stated base range — what pass/fail is
     * judged against (spec: "top of the highest stated range").
     */
    public function highestTop(): ?float
    {
        $tops = array_filter(array_map(fn ($t) => $t['high'] ?? $t['low'], $this->baseTiers()));

        return $tops === [] ? null : max($tops);
    }

    /**
     * The midpoint of the lowest applicable tier — what ranking is
     * based on (spec: "midpoint, using the lowest applicable tier").
     */
    public function lowestTierMidpoint(): ?float
    {
        $tiers = $this->baseTiers();

        if ($tiers === []) {
            return null;
        }

        usort($tiers, fn ($a, $b) => ($a['low'] ?? $a['high']) <=> ($b['low'] ?? $b['high']));
        $lowest = $tiers[0];

        $low = $lowest['low'] ?? $lowest['high'];
        $high = $lowest['high'] ?? $lowest['low'];

        return ($low + $high) / 2;
    }

    public function hasParseableSalary(): bool
    {
        return $this->confidence !== 'none';
    }
}
