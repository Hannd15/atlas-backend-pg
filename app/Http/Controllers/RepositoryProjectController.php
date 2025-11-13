<?php

namespace App\Http\Controllers;

use App\Models\RepositoryProject;
use Illuminate\Http\JsonResponse;

class RepositoryProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $repositoryProjects = RepositoryProject::withDetails()->orderByDesc('updated_at')->get();

        return response()->json($repositoryProjects->map(fn (RepositoryProject $repositoryProject) => $this->transformForIndex($repositoryProject)));
    }

    public function show(RepositoryProject $repositoryProject): JsonResponse
    {
        $repositoryProject->loadMissing(
            'files',
            'project.groups.members.user',
            'project.staff.user',
            'project.proposal.thematicLine'
        );

        return response()->json($this->transformForShow($repositoryProject));
    }

    protected function transformForIndex(RepositoryProject $repositoryProject): array
    {
        return [
            'id' => $repositoryProject->id,
            'title' => $repositoryProject->project?->title ?? $repositoryProject->title,
            'authors' => $this->authorNames($repositoryProject),
            'advisors' => $this->advisorNames($repositoryProject),
            'keywords_es' => $repositoryProject->keywords_es,
            'thematic_line' => $repositoryProject->project?->proposal?->thematicLine?->name,
            'publish_date' => optional($repositoryProject->publish_date)->toDateString(),
            'abstract_es' => $repositoryProject->abstract_es,
            'created_at' => optional($repositoryProject->created_at)->toDateTimeString(),
            'updated_at' => optional($repositoryProject->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(RepositoryProject $repositoryProject): array
    {
        return array_merge(
            $this->transformForIndex($repositoryProject),
            [
                'repository_title' => $repositoryProject->title,
                'project_id' => $repositoryProject->project_id,
                'keywords_en' => $repositoryProject->keywords_en,
                'abstract_en' => $repositoryProject->abstract_en,
                'file_ids' => $repositoryProject->files->pluck('id')->values()->all(),
                'file_names' => $repositoryProject->files->pluck('name')->values()->all(),
            ]
        );
    }

    protected function authorNames(RepositoryProject $repositoryProject): array
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return [];
        }

        $project->loadMissing('groups.members.user');

        return $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function advisorNames(RepositoryProject $repositoryProject): array
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return [];
        }

        $project->loadMissing('staff.user');

        return $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->map(fn ($staff) => $staff->user?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
