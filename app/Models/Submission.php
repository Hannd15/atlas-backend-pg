<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'project_id',
        'submission_date',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'datetime',
        ];
    }

    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'submission_files');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
