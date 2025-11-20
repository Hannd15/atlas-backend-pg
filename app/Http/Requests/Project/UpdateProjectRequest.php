<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'thematic_line_id' => ['sometimes', 'nullable', 'exists:thematic_lines,id'],
            'status_id' => ['sometimes', 'nullable', 'exists:project_statuses,id'],
        ];
    }
}
