<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'state_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function phases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Phase::class, 'period_id');
    }

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AcademicPeriodState::class, 'state_id');
    }

    public function ensureCurrentState(): void
    {
        $this->loadMissing('state');
    }
}
