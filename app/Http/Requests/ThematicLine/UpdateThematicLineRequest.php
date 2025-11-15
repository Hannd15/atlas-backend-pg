<?php

namespace App\Http\Requests\ThematicLine;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThematicLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'rubric_ids' => ['sometimes', 'nullable', 'array'],
            'rubric_ids.*' => ['integer', 'exists:rubrics,id'],
        ];
    }

    public function rubricIds(): ?array
    {
        $validated = $this->validated();

        if (! array_key_exists('rubric_ids', $validated) || $validated['rubric_ids'] === null) {
            return null;
        }

        return collect($validated['rubric_ids'])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
