<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::with('proposal', 'groups.members.user')->orderByDesc('updated_at')->get();

        return response()->json($projects->map(fn (Project $project) => $this->transformForIndex($project)));
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->safe()->only(['title', 'status', 'proposal_id']));

        return response()->json($this->transformForShow($project->load('proposal', 'groups.members.user')), 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load('proposal', 'groups.members.user');

        return response()->json($this->transformForShow($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->safe()->only(['title', 'status', 'proposal_id']));

        $project->load('proposal', 'groups.members.user');

        return response()->json($this->transformForShow($project));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    public function dropdown(): JsonResponse
    {
        $projects = Project::orderBy('title')->get()->map(fn (Project $project) => [
            'value' => $project->id,
            'label' => $project->title,
        ]);

        return response()->json($projects);
    }

    protected function transformForIndex(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status,
            'proposal_id' => $project->proposal_id,
            'group_ids' => $project->groups->pluck('id')->values()->all(),
            'group_names' => $project->groups->pluck('name')->implode(', '),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status,
            'proposal_id' => $project->proposal_id,
            'group_ids' => $project->groups->pluck('id')->values()->all(),
            'group_names' => $project->groups->pluck('name')->implode(', '),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }
}
