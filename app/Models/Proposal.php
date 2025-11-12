<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Proposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'proposal_type_id',
        'proposal_status_id',
        'proposer_id',
        'preferred_director_id',
        'thematic_line_id',
    ];

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function preferredDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_director_id');
    }

    public function thematicLine(): BelongsTo
    {
        return $this->belongsTo(ThematicLine::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProposalType::class, 'proposal_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProposalStatus::class, 'proposal_status_id');
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'proposal_files')->withTimestamps()->orderBy('proposal_files.created_at');
    }
}
