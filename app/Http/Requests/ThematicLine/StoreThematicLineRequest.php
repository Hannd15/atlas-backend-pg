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
        ];
    }
}
