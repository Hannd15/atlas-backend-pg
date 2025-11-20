<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Date;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'title',
        'description',
        'thematic_line_id',
        'status_id',
        'phase_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->phase_id === null) {
                $project->phase_id = static::resolveCurrentPhaseId();
            }
        });

        static::updating(function (Project $project): void {
            if ($project->isDirty('phase_id')) {
                $project->phase_id = $project->getOriginal('phase_id');
            }
        });
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function thematicLine(): BelongsTo
    {
        return $this->belongsTo(ThematicLine::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ProjectGroup::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ProjectStaff::class);
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(ProjectPosition::class, 'project_staff', 'project_id', 'project_position_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'phase_id', 'phase_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function repositoryProject(): HasOne
    {
        return $this->hasOne(RepositoryProject::class);
    }

    protected static function resolveCurrentPhaseId(): int
    {
        $activeStateId = AcademicPeriodState::activeId();

        $phase = Phase::query()
            ->whereDate('start_date', '<=', Date::now())
            ->whereDate('end_date', '>=', Date::now())
            ->whereHas('period', fn ($query) => $query->where('state_id', $activeStateId))
            ->orderBy('start_date')
            ->first();

        if (! $phase) {
            $phase = Phase::query()
                ->whereHas('period', fn ($query) => $query->where('state_id', $activeStateId))
                ->orderBy('start_date')
                ->first();
        }

        if (! $phase) {
            throw new \RuntimeException('Unable to assign a phase to the project because no active academic period exists.');
        }

        return $phase->id;
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class);
    }
}
