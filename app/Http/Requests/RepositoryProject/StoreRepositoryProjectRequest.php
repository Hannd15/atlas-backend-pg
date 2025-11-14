<?php

namespace App\Http\Requests\RepositoryProject;

use Illuminate\Foundation\Http\FormRequest;

class StoreRepositoryProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            'publish_date' => ['nullable', 'date'],
            'keywords_es' => ['nullable', 'string'],
            'keywords_en' => ['nullable', 'string'],
            'abstract_es' => ['nullable', 'string'],
            'abstract_en' => ['nullable', 'string'],
        ];
    }
}
