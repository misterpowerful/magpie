<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Preference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Preference
 */
final class PreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'remote_floor' => $this->remote_floor,
            'onsite_floor' => $this->onsite_floor,
            'hard_min' => $this->hard_min,
            'onsite_states' => $this->onsite_states ?? [],
            'include_titles' => $this->include_titles ?? [],
            'exclude_titles' => $this->exclude_titles ?? [],
            'include_stack' => $this->include_stack ?? [],
            'exclude_stack' => $this->exclude_stack ?? [],
            // null = using the default, which is sent alongside so the
            // form can show it and offer "reset to default".
            'scoring_criteria' => $this->scoring_criteria,
            'default_scoring_criteria' => Preference::DEFAULT_SCORING_CRITERIA,
            'resume_text' => $this->resume_text,
            'resume_filename' => $this->resume_filename,
            'resume_uploaded_at' => $this->resume_uploaded_at?->toIso8601String(),
            'has_resume_file' => $this->resume_path !== null,
            'stale_scored_count' => $this->staleScoredCount(),
        ];
    }

    /**
     * Always 200. Every preferences endpoint lazily creates the row
     * (firstOrCreate), and JsonResource answers 201 Created whenever its
     * model was created during the request — but from the caller's side
     * these are reads and updates of their settings, never a creation.
     */
    public function toResponse($request): JsonResponse
    {
        return parent::toResponse($request)->setStatusCode(200);
    }
}
