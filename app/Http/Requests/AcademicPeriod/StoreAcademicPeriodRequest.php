<?php

namespace App\Http\Requests\AcademicPeriod;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'state_id' => ['nullable', 'exists:academic_period_states,id'],
            'phases' => ['nullable', 'array'],
            'phases.phase_one' => ['nullable', 'array'],
            'phases.phase_one.name' => ['nullable', 'string', 'max:255'],
            'phases.phase_one.deliverables' => ['nullable', 'array'],
            'phases.phase_one.deliverables.*' => ['nullable', 'array'],
            'phases.phase_one.deliverables.*.name' => ['nullable', 'string', 'max:255'],
            'phases.phase_one.deliverables.*.description' => ['nullable', 'string'],
            'phases.phase_one.deliverables.*.due_date' => ['nullable', 'date'],
            'phases.phase_one.deliverables.*.file_ids' => ['nullable', 'array'],
            'phases.phase_one.deliverables.*.file_ids.*' => ['integer', 'exists:files,id'],
            'phases.phase_one.deliverables.*.files' => ['nullable', 'array'],
            'phases.phase_one.deliverables.*.files.*' => ['file'],
            'phases.phase_two' => ['nullable', 'array'],
            'phases.phase_two.name' => ['nullable', 'string', 'max:255'],
            'phases.phase_two.deliverables' => ['nullable', 'array'],
            'phases.phase_two.deliverables.*' => ['nullable', 'array'],
            'phases.phase_two.deliverables.*.name' => ['nullable', 'string', 'max:255'],
            'phases.phase_two.deliverables.*.description' => ['nullable', 'string'],
            'phases.phase_two.deliverables.*.due_date' => ['nullable', 'date'],
            'phases.phase_two.deliverables.*.file_ids' => ['nullable', 'array'],
            'phases.phase_two.deliverables.*.file_ids.*' => ['integer', 'exists:files,id'],
            'phases.phase_two.deliverables.*.files' => ['nullable', 'array'],
            'phases.phase_two.deliverables.*.files.*' => ['file'],
        ];
    }
}
