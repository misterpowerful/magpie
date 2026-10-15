<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Run;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared `?range=last_run|1d|7d|30d` parsing for the dashboard stats panels —
 * each panel toggles its own range independently (spec: per-panel, not a
 * single shared dashboard-wide filter). Anything unrecognized falls back
 * to 1d.
 *
 * last_run is the most recent Run's started_at..ended_at window (or
 * started_at..now while it's still going). Work done outside a run —
 * a manual "Run site" from the admin page, the standalone confirmation
 * scan — falls outside that window by design and only shows up in the
 * day ranges. With no runs yet, last_run matches nothing.
 */
final class StatsRange
{
    public const RANGES = ['last_run', '1d', '7d', '30d'];

    private const DAYS = ['1d' => 1, '7d' => 7, '30d' => 30];

    private function __construct(
        public readonly string $range,
        public readonly ?Carbon $since,
        public readonly ?Carbon $until,
        public readonly ?Run $run,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = $request->query('range');

        if ($range === 'last_run') {
            $run = Run::latest('started_at')->latest('id')->first();

            return new self('last_run', $run?->started_at, $run ? ($run->ended_at ?? Carbon::now()) : null, $run);
        }

        $range = is_string($range) && isset(self::DAYS[$range]) ? $range : '1d';

        return new self($range, Carbon::now()->subDays(self::DAYS[$range]), null, null);
    }

    /**
     * True when the range is last_run but there's no run to scope to —
     * callers should return an empty result rather than query.
     */
    public function isEmpty(): bool
    {
        return $this->range === 'last_run' && $this->run === null;
    }

    /**
     * Constrain $query's $column to this range's window.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(Builder $query, string $column): Builder
    {
        if ($this->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $query->where($column, '>=', $this->since);

        if ($this->until !== null) {
            $query->where($column, '<=', $this->until);
        }

        return $query;
    }

    /**
     * The runs this range covers, oldest first: just the latest one for
     * last_run, otherwise every run that started within the window.
     *
     * @return Collection<int, Run>
     */
    public function runs(): Collection
    {
        if ($this->range === 'last_run') {
            return $this->run ? new Collection([$this->run]) : new Collection;
        }

        return Run::where('started_at', '>=', $this->since)->orderBy('started_at')->orderBy('id')->get();
    }

    /**
     * @return array{range: string, since: ?string, until: ?string, run_id: ?int}
     */
    public function meta(): array
    {
        return [
            'range' => $this->range,
            'since' => $this->since?->toIso8601String(),
            'until' => $this->until?->toIso8601String(),
            'run_id' => $this->run?->id,
        ];
    }
}
