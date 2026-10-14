<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['listing_id', 'tiers', 'snippet', 'parse_confidence', 'status', 'tiered'])]
class ListingSalary extends Model
{
    protected $table = 'listing_salary';

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'tiered' => 'boolean',
        ];
    }
}
