<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proposal_id' => ['nullable', 'exists:proposals,id'],
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
