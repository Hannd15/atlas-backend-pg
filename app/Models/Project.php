<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'title',
        'status',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function groups()
    {
        return $this->hasMany(ProjectGroup::class);
    }

    public function deliverables()
    {
        return $this->hasManyThrough(Deliverable::class, Phase::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
