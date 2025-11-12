<?php

namespace App\Http\Requests\Proposal;

class UpdateProposalRequest extends StoreProposalRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'thematic_line_id' => ['sometimes', 'required', 'exists:thematic_lines,id'],
            'proposer_id' => ['sometimes', 'required', 'exists:users,id'],
            'preferred_director_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'proposal_status_id' => ['sometimes', 'nullable', 'exists:proposal_statuses,id'],
            'file_ids' => ['nullable', 'array'],
            'file_ids.*' => ['integer', 'exists:files,id'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file'],
        ];
    }
}
