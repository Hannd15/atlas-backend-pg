<?php

namespace App\Http\Requests\Rubric;

use Illuminate\Foundation\Http\FormRequest;

class StoreRubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_value' => ['nullable', 'integer'],
            'max_value' => ['nullable', 'integer'],
            'thematic_line_ids' => ['nullable', 'array'],
            'thematic_line_ids.*' => ['integer', 'exists:thematic_lines,id'],
            'deliverable_ids' => ['nullable', 'array'],
            'deliverable_ids.*' => ['integer', 'exists:deliverables,id'],
        ];
    }

    public function thematicLineIds(): ?array
    {
        $ids = $this->safe()->collect('thematic_line_ids');

        if ($ids === null) {
            return null;
        }

        return $ids
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function deliverableIds(): ?array
    {
        $ids = $this->safe()->collect('deliverable_ids');

        if ($ids === null) {
            return null;
        }

        return $ids
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
