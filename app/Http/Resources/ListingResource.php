<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Listing
 */
final class ListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'canonical_url' => $this->canonical_url,
            'company' => $this->company,
            'title' => $this->title,
            'location_text' => $this->location_text,
            'remote_type' => $this->remote_type,
            'state' => $this->state,
            'status' => $this->status,
            'status_reason' => $this->status_reason,
            'applied_at' => $this->applied_at,
            'first_seen_at' => $this->first_seen_at,
            'filter_reason' => $this->filter_reason,
            'filter_flags' => $this->filter_flags,
            'score' => $this->whenLoaded('score', fn () => $this->score ? [
                'rating' => $this->score->rating,
                'reasons' => $this->score->reasons,
                'commentary' => $this->score->commentary,
            ] : null),
            'salary' => $this->whenLoaded('salary', fn () => $this->salary ? [
                'tiers' => $this->salary->tiers,
                'snippet' => $this->salary->snippet,
                'status' => $this->salary->status,
                'tiered' => $this->salary->tiered,
                'parse_confidence' => $this->salary->parse_confidence,
            ] : null),
            'sources' => $this->whenLoaded(
                'sources',
                fn () => $this->sources->map(fn ($source) => $source->site?->name ?? 'email')->unique()->values(),
            ),
        ];
    }
}
