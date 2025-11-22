<?php

namespace App\Http\Requests\ProjectGroup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'nullable', 'exists:projects,id'],
            'member_user_ids' => ['sometimes', 'nullable', 'array'],
            'member_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function memberUserIds(): ?array
    {
        $validated = $this->validated();

        if (! array_key_exists('member_user_ids', $validated) || $validated['member_user_ids'] === null) {
            return null;
        }

        return collect($validated['member_user_ids'])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
