<?php

namespace App\Http\Requests\ThematicLine;

use Illuminate\Foundation\Http\FormRequest;

class StoreThematicLineRequest extends FormRequest
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
            'rubric_ids' => ['nullable', 'array'],
            'rubric_ids.*' => ['integer', 'exists:rubrics,id'],
        ];
    }

    public function rubricIds(): ?array
    {
        $ids = $this->safe()->collect('rubric_ids');

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
