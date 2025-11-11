<?php

namespace App\Http\Requests\ProjectGroup;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'member_user_ids' => ['nullable', 'array'],
            'member_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function memberUserIds(): ?array
    {
        $ids = $this->safe()->collect('member_user_ids');

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
