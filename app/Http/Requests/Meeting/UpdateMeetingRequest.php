<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meeting_date' => ['sometimes', 'required', 'date'],
            'observations' => ['sometimes', 'nullable', 'string'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i', 'after:start_time', 'required_with:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'The end time must be after the start time.',
        ];
    }
}
