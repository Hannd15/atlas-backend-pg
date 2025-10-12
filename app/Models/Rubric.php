<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rubric extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'min_value',
        'max_value',
    ];

    public function thematicLines()
    {
        return $this->belongsToMany(ThematicLine::class, 'rubric_thematic_lines');
    }

    public function deliverables()
    {
        return $this->belongsToMany(Deliverable::class, 'rubric_deliverables');
    }
}
