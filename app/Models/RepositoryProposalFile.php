<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryProposalFile extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'repository_proposal_id',
        'file_id',
    ];

    public function repositoryProposal(): BelongsTo
    {
        return $this->belongsTo(RepositoryProposal::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
