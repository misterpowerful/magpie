<?php

namespace App\Models;

use Database\Factories\PreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
