<?php

namespace App\Http\Requests\Rubric;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRubricRequest extends FormRequest
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
            'min_value' => ['sometimes', 'nullable', 'integer'],
            'max_value' => ['sometimes', 'nullable', 'integer'],
            'thematic_line_ids' => ['sometimes', 'nullable', 'array'],
            'thematic_line_ids.*' => ['integer', 'exists:thematic_lines,id'],
            'deliverable_ids' => ['sometimes', 'nullable', 'array'],
            'deliverable_ids.*' => ['integer', 'exists:deliverables,id'],
        ];
    }

    public function thematicLineIds(): ?array
    {
        $validated = $this->validated();

        if (! array_key_exists('thematic_line_ids', $validated) || $validated['thematic_line_ids'] === null) {
            return null;
        }

        return collect($validated['thematic_line_ids'])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function deliverableIds(): ?array
    {
        $validated = $this->validated();

        if (! array_key_exists('deliverable_ids', $validated) || $validated['deliverable_ids'] === null) {
            return null;
        }

        return collect($validated['deliverable_ids'])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
