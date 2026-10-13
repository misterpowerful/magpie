<?php

namespace App\Models;

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
    /** @use HasFactory<\Database\Factories\PreferenceFactory> */
    use HasFactory;

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
