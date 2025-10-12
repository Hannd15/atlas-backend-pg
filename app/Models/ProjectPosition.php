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

    public function staff()
    {
        return $this->hasMany(ProjectStaff::class);
    }

    public function eligibleUsers()
    {
        return $this->belongsToMany(User::class, 'user_project_eligibilities');
    }
}
