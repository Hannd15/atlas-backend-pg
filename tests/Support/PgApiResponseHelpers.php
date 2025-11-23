<?php

namespace Tests\Support;

use App\Models\AcademicPeriod;
use App\Models\Deliverable;
use App\Models\DeliverableFile;
use App\Models\Evaluation;
use App\Models\File;
use App\Models\Phase;
use App\Models\ProjectPosition;
use App\Models\Rubric;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

trait PgApiResponseHelpers
{
    protected function academicPeriodResource(AcademicPeriod $period): array
    {
        $period->loadMissing('state');

        return [
            'id' => $period->id,
            'name' => $period->name,
            'start_date' => optional($period->start_date)->toDateString(),
            'end_date' => optional($period->end_date)->toDateString(),
            'state_id' => $period->state?->id,
            'state_name' => $period->state?->name,
            'state_description' => $period->state?->description,
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
            'deliverable_ids' => $file->deliverables->pluck('id')->values()->all(),
            'submission_ids' => $file->submissions->pluck('id')->values()->all(),
            'repository_project_ids' => $file->repositoryProjects->pluck('id')->values()->all(),
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
        return $positions->map(function (ProjectPosition $position) {
            return [
                'id' => $position->id,
                'name' => $position->name,
                'eligible_user_names' => $position->eligibleUsers->pluck('name')->implode(', '),
                'eligible_user_count' => $position->eligibleUsers->count(),
                'staff_count' => $position->staff->count(),
                'created_at' => optional($position->created_at)->toDateTimeString(),
                'updated_at' => optional($position->updated_at)->toDateTimeString(),
            ];
        })->all();
    }

    protected function projectPositionShowResource(ProjectPosition $projectPosition): array
    {
        $projectPosition->loadMissing('eligibleUsers', 'staff');

        return [
            'id' => $projectPosition->id,
            'name' => $projectPosition->name,
            'eligible_user_ids' => $projectPosition->eligibleUsers->pluck('id')->values()->all(),
            'staff_ids' => $projectPosition->staff->pluck('id')->values()->all(),
            'created_at' => optional($projectPosition->created_at)->toDateTimeString(),
            'updated_at' => optional($projectPosition->updated_at)->toDateTimeString(),
        ];
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

    protected function submissionResource(Submission $submission): array
    {
        $submission->loadMissing('deliverable.phase.period', 'project', 'files', 'evaluations');

        return [
            'id' => $submission->id,
            'deliverable_id' => $submission->deliverable_id,
            'project_id' => $submission->project_id,
            'submission_date' => optional($submission->submission_date)->toDateTimeString(),
            'comment' => $submission->comment,
            'deliverable_name' => $submission->deliverable?->name,
            'project_title' => $submission->project?->title,
            'phase_name' => $submission->deliverable?->phase?->name,
            'period_name' => $submission->deliverable?->phase?->period?->name,
            'file_count' => $submission->files->count(),
            'evaluation_count' => $submission->evaluations->count(),
            'created_at' => optional($submission->created_at)->toDateTimeString(),
            'updated_at' => optional($submission->updated_at)->toDateTimeString(),
        ];
    }

    protected function evaluationResource(Evaluation $evaluation): array
    {
        $evaluation->loadMissing('user', 'evaluator', 'rubric');

        return [
            'id' => $evaluation->id,
            'submission_id' => $evaluation->submission_id,
            'user_id' => $evaluation->user_id,
            'evaluator_id' => $evaluation->evaluator_id,
            'rubric_id' => $evaluation->rubric_id,
            'grade' => $evaluation->grade,
            'comments' => $evaluation->comments,
            'evaluation_date' => optional($evaluation->evaluation_date)->toDateTimeString(),
            'user_name' => $evaluation->user?->name,
            'evaluator_name' => $evaluation->evaluator?->name,
            'rubric_name' => $evaluation->rubric?->name,
            'created_at' => optional($evaluation->created_at)->toDateTimeString(),
            'updated_at' => optional($evaluation->updated_at)->toDateTimeString(),
        ];
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

    protected function userEligibilityByPositionUsersDropdownArray(ProjectPosition $position): array
    {
        $position->loadMissing(['eligibleUsers' => fn ($query) => $query->orderBy('name')]);

        return $position->eligibleUsers
            ->sortBy('name')
            ->values()
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->all();
    }
}
