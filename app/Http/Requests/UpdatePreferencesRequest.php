<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePreferencesRequest extends FormRequest
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
            'remote_floor' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000000'],
            'onsite_floor' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000000'],
            'hard_min' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000000'],
            'onsite_states' => ['sometimes', 'array', 'max:60'],
            'onsite_states.*' => ['string', 'size:2', 'alpha'],
            'include_titles' => ['sometimes', 'array', 'max:100'],
            'include_titles.*' => ['string', 'max:200'],
            'exclude_titles' => ['sometimes', 'array', 'max:100'],
            'exclude_titles.*' => ['string', 'max:200'],
            'include_stack' => ['sometimes', 'array', 'max:100'],
            'include_stack.*' => ['string', 'max:100'],
            'exclude_stack' => ['sometimes', 'array', 'max:100'],
            'exclude_stack.*' => ['string', 'max:100'],
            // Empty/null resets to the default criteria.
            'scoring_criteria' => ['sometimes', 'nullable', 'string', 'max:10000'],
            // Hand-corrections to the text extracted from the uploaded file.
            'resume_text' => ['sometimes', 'nullable', 'string', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('onsite_states'))) {
            $this->merge(['onsite_states' => array_map(fn ($s) => is_string($s) ? strtoupper(trim($s)) : $s, $this->input('onsite_states'))]);
        }
    }
}
