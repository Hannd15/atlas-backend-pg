<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'extension',
        'url',
    ];

    public function deliverables()
    {
        return $this->belongsToMany(Deliverable::class, 'deliverable_files');
    }

    public function submissions()
    {
        return $this->belongsToMany(Submission::class, 'submission_files');
    }

    public function repositoryProjects()
    {
        return $this->belongsToMany(RepositoryProject::class, 'repository_project_files', 'file_id', 'repository_item_id');
    }

    public function repositoryProposals()
    {
        return $this->belongsToMany(RepositoryProposal::class, 'repository_proposal_files', 'file_id', 'repository_proposal_id');
    }
}
