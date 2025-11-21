<?php

namespace App\Http\Requests\Proposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'thematic_line_id' => ['required', 'exists:thematic_lines,id'],
            'preferred_director_id' => ['nullable', 'exists:users,id'],
            'proposal_status_id' => ['nullable', 'exists:proposal_statuses,id'],
            // File management is handled by dedicated proposal file endpoints now.
        ];
    }

    /**
     * @return array<int, int>|null
     */
    public function fileIds(): ?array
    {
        if (! $this->exists('file_ids')) {
            return null;
        }

        $ids = $this->input('file_ids');

        if ($ids === null) {
            return null;
        }

        return collect($ids)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        if (! $this->hasFile('files')) {
            return [];
        }

        $files = $this->file('files');

        return collect(Arr::wrap($files))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();
    }
}
