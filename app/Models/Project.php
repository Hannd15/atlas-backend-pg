<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function groups(): HasMany
    {
        return $this->hasMany(ProjectGroup::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ProjectStaff::class);
    }

    public function deliverables()
    {
        return $this->hasManyThrough(Deliverable::class, Phase::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
