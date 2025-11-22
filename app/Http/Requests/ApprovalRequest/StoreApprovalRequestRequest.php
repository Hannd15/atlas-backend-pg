<?php

namespace App\Http\Requests\ApprovalRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actionKeys = array_keys(config('approval.actions', []));

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'action_key' => ['required', 'string', Rule::in($actionKeys)],
            'action_payload' => ['nullable', 'array'],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array{title:string,description:?string,action_key:string,action_payload:array}
     */
    public function sanitizedPayload(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'description' => $this->input('description'),
            'action_key' => $this->string('action_key')->toString(),
            'action_payload' => $this->input('action_payload', []),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function recipientIds(): array
    {
        return collect($this->input('recipient_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
