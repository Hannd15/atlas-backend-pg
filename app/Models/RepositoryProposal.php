<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepositoryProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'title',
        'description',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'repository_proposal_files', 'repository_proposal_id', 'file_id');
    }
}
