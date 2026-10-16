<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actions\Preferences;

use App\Http\Requests\UploadResumeRequest;
use App\Http\Resources\PreferenceResource;
use App\Models\Preference;
use App\Support\ResumeTextExtractor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Replaces the resume the scorer measures against: keeps the uploaded
 * file (so the original can be downloaded again) and its extracted text
 * (what the scorer reads, correctable on the Resume tab). Only the
 * latest upload is kept.
 */
final class UploadResumeAction
{
    public function __invoke(UploadResumeRequest $request, ResumeTextExtractor $extractor): PreferenceResource
    {
        $file = $request->file('resume');
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $text = $extractor->extract($file->getRealPath(), $extension);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['resume' => [$e->getMessage()]]);
        }

        $preferences = Preference::forUser($request->user());
        $previousPath = $preferences->resume_path;
        $path = $file->storeAs("resumes/{$request->user()->id}", "resume-".Carbon::now()->format('YmdHis').".{$extension}", 'local');

        $preferences->update([
            'resume_text' => $text,
            'resume_path' => $path,
            'resume_filename' => $file->getClientOriginalName(),
            'resume_uploaded_at' => Carbon::now(),
        ]);

        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return new PreferenceResource($preferences->refresh());
    }
}
