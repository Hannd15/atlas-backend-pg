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
            'phases' => ['sometimes', 'array'],
            'phases.phase_one' => ['sometimes', 'array'],
            'phases.phase_one.name' => ['sometimes', 'string', 'max:255'],
            'phases.phase_one.deliverables' => ['sometimes', 'array'],
            'phases.phase_one.deliverables.*' => ['sometimes', 'array'],
            'phases.phase_one.deliverables.*.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phases.phase_one.deliverables.*.description' => ['sometimes', 'nullable', 'string'],
            'phases.phase_one.deliverables.*.due_date' => ['sometimes', 'nullable', 'date'],
            'phases.phase_one.deliverables.*.file_ids' => ['sometimes', 'array'],
            'phases.phase_one.deliverables.*.file_ids.*' => ['integer', 'exists:files,id'],
            'phases.phase_one.deliverables.*.files' => ['sometimes', 'array'],
            'phases.phase_one.deliverables.*.files.*' => ['file'],
            'phases.phase_two' => ['sometimes', 'array'],
            'phases.phase_two.name' => ['sometimes', 'string', 'max:255'],
            'phases.phase_two.deliverables' => ['sometimes', 'array'],
            'phases.phase_two.deliverables.*' => ['sometimes', 'array'],
            'phases.phase_two.deliverables.*.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phases.phase_two.deliverables.*.description' => ['sometimes', 'nullable', 'string'],
            'phases.phase_two.deliverables.*.due_date' => ['sometimes', 'nullable', 'date'],
            'phases.phase_two.deliverables.*.file_ids' => ['sometimes', 'array'],
            'phases.phase_two.deliverables.*.file_ids.*' => ['integer', 'exists:files,id'],
            'phases.phase_two.deliverables.*.files' => ['sometimes', 'array'],
            'phases.phase_two.deliverables.*.files.*' => ['file'],
        ];
    }
}
