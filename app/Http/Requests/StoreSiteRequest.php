<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Intake\IntakeDriverRegistry;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'driver' => ['required', Rule::in(Site::DRIVERS)],
            'config' => ['nullable', 'array'],
            'config.adapter' => [
                Rule::requiredIf(in_array($this->input('driver'), ['rss', 'api'], true)),
                Rule::in(IntakeDriverRegistry::knownAdapters()),
            ],
            'enabled' => ['boolean'],
        ];
    }
}
