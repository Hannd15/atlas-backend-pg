<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'type',
        'proposer_id',
        'preferred_director_id',
        'thematic_line_id',
    ];

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function preferredDirector()
    {
        return $this->belongsTo(User::class, 'preferred_director_id');
    }

    public function thematicLine()
    {
        return $this->belongsTo(ThematicLine::class);
    }

    public function repositoryItem()
    {
        return $this->hasOne(RepositoryProposal::class, 'proposal_id');
    }
}
