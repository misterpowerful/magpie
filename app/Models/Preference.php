<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, string>|null $onsite_states
 * @property array<int, string>|null $include_titles
 * @property array<int, string>|null $exclude_titles
 * @property array<int, string>|null $include_stack
 * @property array<int, string>|null $exclude_stack
 */
#[Fillable([
    'user_id',
    'remote_floor',
    'onsite_floor',
    'hard_min',
    'onsite_states',
    'include_titles',
    'exclude_titles',
    'include_stack',
    'exclude_stack',
    'resume_text',
])]
class Preference extends Model
{
    /** @use HasFactory<PreferenceFactory> */
    use HasFactory;

    /**
     * Mirrors the migration's column defaults so a freshly-instantiated
     * (unsaved or just-created) model reflects them immediately, since
     * Eloquent doesn't reload DB-applied defaults after an insert.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'remote_floor' => 150000,
        'onsite_floor' => 180000,
        'hard_min' => 140000,
        'onsite_states' => '["IL"]',
    ];

    protected static function booted(): void
    {
        static::saving(function (Preference $preference) {
            $preference->resume_hash = $preference->resume_text
                ? hash('sha256', $preference->resume_text)
                : null;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Hash of the scoring-relevant preference fields (everything except
     * resume_text/resume_hash, which are tracked separately) — used to
     * decide whether a listing needs rescoring (spec section 7:
     * "Rescore only if the resume or preferences hash changed").
     */
    public function scoringHash(): string
    {
        return hash('sha256', json_encode([
            'remote_floor' => $this->remote_floor,
            'onsite_floor' => $this->onsite_floor,
            'hard_min' => $this->hard_min,
            'onsite_states' => $this->onsite_states,
            'include_titles' => $this->include_titles,
            'exclude_titles' => $this->exclude_titles,
            'include_stack' => $this->include_stack,
            'exclude_stack' => $this->exclude_stack,
        ]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onsite_states' => 'array',
            'include_titles' => 'array',
            'exclude_titles' => 'array',
            'include_stack' => 'array',
            'exclude_stack' => 'array',
        ];
    }
}
