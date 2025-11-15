<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'user_id',
        'evaluator_id',
        'rubric_id',
        'grade',
        'comments',
        'evaluation_date',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'float',
            'evaluation_date' => 'datetime',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function rubric()
    {
        return $this->belongsTo(Rubric::class);
    }
}
