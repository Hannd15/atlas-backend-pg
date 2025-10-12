<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStaff extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'project_position_id',
        'status',
    ];

    public $incrementing = false;

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function position()
    {
        return $this->belongsTo(ProjectPosition::class, 'project_position_id');
    }
}
