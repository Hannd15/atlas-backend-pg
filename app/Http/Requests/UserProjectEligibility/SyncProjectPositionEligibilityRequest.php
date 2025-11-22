<?php

namespace App\Http\Requests\UserProjectEligibility;

use Illuminate\Foundation\Http\FormRequest;

class SyncProjectPositionEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'At least one user must be provided.',
            'user_ids.array' => 'The user_ids field must be an array of user identifiers.',
            'user_ids.*.exists' => 'One or more of the selected users do not exist.',
        ];
    }
}
