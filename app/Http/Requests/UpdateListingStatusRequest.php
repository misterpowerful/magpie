<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateListingStatusRequest extends FormRequest
{
    /**
     * Ownership is checked via ListingPolicy in UpdateListingStatusAction
     * — this FormRequest only validates input shape.
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
            'status' => ['required', Rule::in(array_diff(Listing::STATUSES, ['new']))],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
