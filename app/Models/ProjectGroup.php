<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class, 'group_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_members', 'group_id', 'user_id')->withTimestamps();
    }
}
