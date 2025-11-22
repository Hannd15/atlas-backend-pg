<?php

namespace App\Http\Requests\ApprovalRequest;

use App\Models\ApprovalRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DecideApprovalRequestRequest extends FormRequest
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
        return [
            'decision' => ['required', 'string', Rule::in([
                ApprovalRequest::DECISION_APPROVED,
                ApprovalRequest::DECISION_REJECTED,
            ])],
        ];
    }

    public function decision(): string
    {
        return Str::lower($this->string('decision')->toString());
    }
}
