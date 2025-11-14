<?php

namespace App\Http\Requests\RepositoryProject;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRepositoryProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'publish_date' => ['sometimes', 'nullable', 'date'],
            'keywords_es' => ['sometimes', 'nullable', 'string'],
            'keywords_en' => ['sometimes', 'nullable', 'string'],
            'abstract_es' => ['sometimes', 'nullable', 'string'],
            'abstract_en' => ['sometimes', 'nullable', 'string'],
            'file_ids' => ['sometimes', 'nullable', 'array'],
            'file_ids.*' => ['integer', 'exists:files,id'],
            'files' => ['sometimes', 'nullable', 'array'],
            'files.*' => ['file'],
        ];
    }
}
