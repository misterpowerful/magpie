<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Preferences;

use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadResumeAction
{
    public function __invoke(Request $request): StreamedResponse
    {
        $preferences = Preference::where('user_id', $request->user()->id)->first();

        abort_if($preferences?->resume_path === null || ! Storage::disk('local')->exists($preferences->resume_path), 404);

        return Storage::disk('local')->download($preferences->resume_path, $preferences->resume_filename);
    }
}
