<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriodState extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function periods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AcademicPeriod::class, 'state_id');
    }
}
