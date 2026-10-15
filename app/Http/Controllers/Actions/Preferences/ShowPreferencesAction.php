<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Preferences;

use App\Http\Resources\PreferenceResource;
use App\Models\Preference;
use Illuminate\Http\Request;

final class ShowPreferencesAction
{
    public function __invoke(Request $request): PreferenceResource
    {
        return new PreferenceResource(Preference::firstOrCreate(['user_id' => $request->user()->id]));
    }
}
