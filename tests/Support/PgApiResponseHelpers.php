<?php

namespace Tests\Support;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\DeliverableFile;
use App\Models\File;
use App\Models\Phase;
use App\Models\ProjectPosition;
use App\Models\Rubric;
use App\Models\User;
use Illuminate\Support\Collection;

trait PgApiResponseHelpers
{
    protected function academicPeriodResource(AcademicPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'state' => $period->state ? [
                'id' => $period->state->id,
                'name' => $period->state->name,
                'description' => $period->state->description,
            ] : null,
            'created_at' => optional($period->created_at)->toDateTimeString(),
            'updated_at' => optional($period->updated_at)->toDateTimeString(),
        ];
    }

    protected function deliverableResource(Deliverable $deliverable): array
    {
        $deliverable->loadMissing('phase.period', 'files', 'rubrics');

        return [
            'id' => $deliverable->id,
            'name' => $deliverable->name,
            'description' => $deliverable->description,
            'due_date' => optional($deliverable->due_date)->toDateTimeString(),
            'phase' => $deliverable->phase ? [
                'id' => $deliverable->phase->id,
                'name' => $deliverable->phase->name,
                'period' => $deliverable->phase->period ? [
                    'id' => $deliverable->phase->period->id,
                    'name' => $deliverable->phase->period->name,
                ] : null,
            ] : null,
            'files' => $deliverable->files->map(fn (File $file) => [
                'id' => $file->id,
                'name' => $file->name,
                'extension' => $file->extension,
                'url' => $file->url,
            ])->values()->all(),
            'file_ids' => $deliverable->files->pluck('id')->values()->all(),
            'rubrics' => $deliverable->rubrics->map(fn (Rubric $rubric) => [
                'id' => $rubric->id,
                'name' => $rubric->name,
                'description' => $rubric->description,
                'min_value' => $rubric->min_value,
                'max_value' => $rubric->max_value,
            ])->values()->all(),
            'rubric_ids' => $deliverable->rubrics->pluck('id')->values()->all(),
            'rubric_names' => $deliverable->rubrics->pluck('name')->implode(', '),
            'created_at' => optional($deliverable->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverable->updated_at)->toDateTimeString(),
        ];
    }

    protected function deliverableFileResource(DeliverableFile $deliverableFile): array
    {
        $deliverableFile->loadMissing('deliverable.phase.period', 'file');

        $deliverable = $deliverableFile->deliverable;
        $phase = $deliverable?->phase;
        $period = $phase?->period;

        return [
            'deliverable_id' => $deliverableFile->deliverable_id,
            'file_id' => $deliverableFile->file_id,
            'deliverable_name' => $deliverable?->name,
            'phase_name' => $phase?->name,
            'academic_period_name' => $period?->name,
            'file_name' => $deliverableFile->file?->name,
            'created_at' => optional($deliverableFile->created_at)->toDateTimeString(),
            'updated_at' => optional($deliverableFile->updated_at)->toDateTimeString(),
        ];
    }

    protected function fileResource(File $file): array
    {
        $file->loadMissing(
            'deliverables.phase.period',
            'submissions',
            'repositoryProjects',
            'proposals'
        );

        return [
            'id' => $file->id,
            'name' => $file->name,
            'extension' => $file->extension,
            'url' => $file->url,
            'disk' => $file->disk,
            'path' => $file->path,
            'deliverables' => $file->deliverables->map(fn (Deliverable $deliverable) => [
                'id' => $deliverable->id,
                'name' => $deliverable->name,
                'phase' => $deliverable->phase ? [
                    'id' => $deliverable->phase->id,
                    'name' => $deliverable->phase->name,
                    'period' => $deliverable->phase->period ? [
                        'id' => $deliverable->phase->period->id,
                        'name' => $deliverable->phase->period->name,
                    ] : null,
                ] : null,
            ])->values()->all(),
            'deliverable_ids' => $file->deliverables->pluck('id')->values()->all(),
            'submissions' => $file->submissions->map(fn ($submission) => [
                'id' => $submission->id,
            ])->values()->all(),
            'submission_ids' => $file->submissions->pluck('id')->values()->all(),
            'repository_projects' => $file->repositoryProjects->map(fn ($project) => [
                'id' => $project->id,
                'title' => $project->title,
            ])->values()->all(),
            'repository_project_ids' => $file->repositoryProjects->pluck('id')->values()->all(),
            'proposals' => $file->proposals->map(fn ($proposal) => [
                'id' => $proposal->id,
                'title' => $proposal->title,
            ])->values()->all(),
            'proposal_ids' => $file->proposals->pluck('id')->values()->all(),
            'created_at' => optional($file->created_at)->toDateTimeString(),
            'updated_at' => optional($file->updated_at)->toDateTimeString(),
        ];
    }

    protected function phaseIndexArray(Collection $phases): array
    {
        $phases->each(function (Phase $phase): void {
            $phase->period_names = $phase->period ? $phase->period->name : '';
            $phase->deliverable_names = $phase->deliverables->pluck('name')->implode(', ');
        });

        return $phases->map(fn (Phase $phase) => $phase->toArray())->all();
    }

    protected function userIndexArray(Collection $users): array
    {
        $users->each(function (User $user): void {
            $user->project_position_eligibility_names = $user->eligiblePositions->pluck('name')->implode(', ');
            $user->proposal_names = $user->proposals->pluck('title')
                ->merge($user->preferredProposals->pluck('title'))
                ->filter()
                ->unique()
                ->implode(', ');
        });

        return $users->map(fn (User $user) => $user->toArray())->all();
    }

    protected function userShowResource(User $user): array
    {
        $user->loadMissing('eligiblePositions', 'proposals', 'preferredProposals');
        $user->project_position_eligibility_ids = $user->eligiblePositions->pluck('id');
        $user->proposal_names = $user->proposals->pluck('title')
            ->merge($user->preferredProposals->pluck('title'))
            ->filter()
            ->unique()
            ->implode(', ');

        return $user->toArray();
    }

    protected function userDropdownArray(Collection $users): array
    {
        return $users->map(function (User $user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
                'project_position_eligibility_names' => $user->eligiblePositions->pluck('name')->implode(', '),
                'proposal_names' => $user->proposals->pluck('title')
                    ->merge($user->preferredProposals->pluck('title'))
                    ->filter()
                    ->unique()
                    ->implode(', '),
            ];
        })->values()->all();
    }

    protected function projectPositionIndexArray(Collection $positions): array
    {
        $positions->each(function (ProjectPosition $position): void {
            $position->eligible_user_names = $position->eligibleUsers->pluck('name')->implode(', ');
            $position->staff_names = $position->staff->map(fn ($staff) => "Staff #{$staff->id}")->implode(', ');
        });

        return $positions->map(fn (ProjectPosition $position) => $position->toArray())->all();
    }

    protected function projectPositionShowResource(ProjectPosition $projectPosition): array
    {
        $projectPosition->loadMissing('eligibleUsers', 'staff');
        $projectPosition->eligible_user_ids = $projectPosition->eligibleUsers->pluck('id');
        $projectPosition->staff_ids = $projectPosition->staff->pluck('id');

        return $projectPosition->toArray();
    }

    protected function projectPositionDropdownArray(Collection $positions): array
    {
        return $positions->map(fn (ProjectPosition $position) => [
            'value' => $position->id,
            'label' => $position->name,
        ])->values()->all();
    }

    protected function deliverableDropdownArray(Collection $deliverables): array
    {
        return $deliverables->map(fn (Deliverable $deliverable) => [
            'value' => $deliverable->id,
            'label' => $deliverable->name,
        ])->values()->all();
    }

    protected function fileDropdownArray(Collection $files): array
    {
        return $files->map(fn (File $file) => [
            'value' => $file->id,
            'label' => $file->name,
        ])->values()->all();
    }

    protected function userEligibilityByUserArray(?Collection $users = null): array
    {
        $users ??= User::with(['eligiblePositions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $users->loadMissing(['eligiblePositions' => fn ($query) => $query->orderBy('name')]);

        return $users->sortBy('name')->values()->map(function (User $user) {
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'project_position_names' => $user->eligiblePositions->pluck('name')->implode(', '),
            ];
        })->all();
    }

    protected function userEligibilityByPositionArray(?Collection $positions = null): array
    {
        $positions ??= ProjectPosition::with(['eligibleUsers' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $positions->loadMissing(['eligibleUsers' => fn ($query) => $query->orderBy('name')]);

        return $positions->sortBy('name')->values()->map(function (ProjectPosition $position) {
            return [
                'project_position_id' => $position->id,
                'project_position_name' => $position->name,
                'user_names' => $position->eligibleUsers->pluck('name')->implode(', '),
            ];
        })->all();
    }

    protected function userEligibilityByUserDropdownArray(?Collection $users = null): array
    {
        return collect($this->userEligibilityByUserArray($users))->map(function (array $item) {
            $label = $item['project_position_names'] !== ''
                ? $item['user_name'].' - '.$item['project_position_names']
                : $item['user_name'];

            return [
                'value' => $item['user_id'],
                'label' => $label,
            ];
        })->values()->all();
    }

    protected function userEligibilityByPositionDropdownArray(?Collection $positions = null): array
    {
        return collect($this->userEligibilityByPositionArray($positions))->map(function (array $item) {
            $label = $item['user_names'] !== ''
                ? $item['project_position_name'].' - '.$item['user_names']
                : $item['project_position_name'];

            return [
                'value' => $item['project_position_id'],
                'label' => $label,
            ];
        })->values()->all();
    }
}
