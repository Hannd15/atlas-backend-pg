<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function staff(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProjectStaff::class);
    }

    public function eligibleUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_project_eligibilities', 'project_position_id', 'user_id');
    }
}
