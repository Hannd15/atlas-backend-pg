<?php

namespace App\Http\Requests\AcademicPeriod;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'state_id' => ['sometimes', 'nullable', 'exists:academic_period_states,id'],
        ];
    }
}
