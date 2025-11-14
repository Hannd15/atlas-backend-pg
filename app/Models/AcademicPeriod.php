<?php

namespace App\Models;

use App\Models\AcademicPeriodState;
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

    protected static function booted(): void
    {
        static::saving(function (AcademicPeriod $period): void {
            $period->resolveState();
        });
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
        $stateId = $this->currentStateId();

        if ($stateId === null || $this->state_id === $stateId) {
            return;
        }

        $this->state_id = $stateId;
        $this->saveQuietly();
        $this->loadMissing('state');
    }

    private function resolveState(): void
    {
        $stateId = $this->currentStateId();

        if ($stateId !== null) {
            $this->state_id = $stateId;
        }
    }

    private function currentStateId(): ?int
    {
        if (! $this->end_date) {
            return AcademicPeriodState::activeId();
        }

        return $this->end_date->isPast()
            ? AcademicPeriodState::finishedId()
            : AcademicPeriodState::activeId();
    }
}
