<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Preferences;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\PreferenceResource;
use App\Models\Preference;

/**
 * Saves scoring criteria, filter preferences, and resume-text edits.
 * Nothing is rescored here — that's an explicit, usage-costing action
 * (RescoreStaleListingsAction) the owner chooses to take.
 */
final class UpdatePreferencesAction
{
    public function __invoke(UpdatePreferencesRequest $request): PreferenceResource
    {
        $preferences = Preference::firstOrCreate(['user_id' => $request->user()->id]);
        $data = $request->validated();

        foreach (['scoring_criteria', 'resume_text'] as $field) {
            if (array_key_exists($field, $data) && trim((string) $data[$field]) === '') {
                $data[$field] = null;
            }
        }

        $preferences->update($data);

        return new PreferenceResource($preferences->refresh());
    }
}
