<?php

namespace App\Http\Requests;

use App\Intake\IntakeDriverRegistry;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('site')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $driver = $this->input('driver', $this->route('site')->driver);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'max:2048'],
            'driver' => ['sometimes', 'required', Rule::in(Site::DRIVERS)],
            'config' => ['sometimes', 'nullable', 'array'],
            'config.adapter' => [
                'sometimes',
                Rule::requiredIf($this->has('config') && in_array($driver, ['rss', 'api'], true)),
                Rule::in(IntakeDriverRegistry::knownAdapters()),
            ],
            'enabled' => ['boolean'],
        ];
    }
}
