<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relations added for project domain
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'proposer_id');
    }

    public function preferredProposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'preferred_director_id');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function projectStaff(): HasMany
    {
        return $this->hasMany(ProjectStaff::class);
    }

    public function eligiblePositions(): BelongsToMany
    {
        return $this->belongsToMany(ProjectPosition::class, 'user_project_eligibilities', 'user_id', 'project_position_id');
    }

    public function meetingsCreated(): HasMany
    {
        return $this->hasMany(Meeting::class, 'created_by');
    }

    public function meetingsAttended(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_attendees', 'user_id', 'meeting_id');
    }

    public function evaluationsAuthored(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }
}
