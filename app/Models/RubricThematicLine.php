<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricThematicLine extends Model
{
    public $incrementing = false;

    protected $fillable = [
        'rubric_id',
        'thematic_line_id',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function thematicLine(): BelongsTo
    {
        return $this->belongsTo(ThematicLine::class);
    }
}
