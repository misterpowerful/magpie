<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ResumeTextExtractor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UploadResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resume' => ['required', 'file', 'max:5120', 'extensions:'.implode(',', ResumeTextExtractor::EXTENSIONS)],
        ];
    }
}
