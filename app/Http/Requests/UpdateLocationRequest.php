<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for updating existing locations.
 *
 * Includes validation to prevent circular parent-child relationships
 * (a location cannot be moved inside itself or its descendants).
 */
class UpdateLocationRequest extends FormRequest
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
        /** @var Location $location */
        $location = $this->route('location');

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'exists:locations,id',
                function ($attribute, $value, $fail) use ($location) {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === $location->id) {
                        $fail('The location cannot be its own parent.');

                        return;
                    }

                    if ($location->isDescendant((int) $value)) {
                        $fail('The location cannot be moved inside one of its children.');
                    }
                },
            ],
        ];
    }
}
