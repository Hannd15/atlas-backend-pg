<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThematicLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trl_expected',
        'abet_criteria',
        'min_score',
    ];

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    public function rubrics()
    {
        return $this->belongsToMany(Rubric::class, 'rubric_thematic_lines');
    }
}
