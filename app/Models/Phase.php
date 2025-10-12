<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Phase extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'name',
        'start_date',
        'end_date',
    ];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'period_id');
    }

    public function deliverables()
    {
        return $this->hasMany(Deliverable::class);
    }
}
