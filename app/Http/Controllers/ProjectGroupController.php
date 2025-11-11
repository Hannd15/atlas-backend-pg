<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectGroup\StoreProjectGroupRequest;
use App\Http\Requests\ProjectGroup\UpdateProjectGroupRequest;
use App\Models\GroupMember;
use App\Models\ProjectGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = ProjectGroup::with('project', 'users')->orderByDesc('updated_at')->get();

        return response()->json($groups->map(fn (ProjectGroup $group) => $this->transformForIndex($group)));
    }

    public function store(StoreProjectGroupRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $group = ProjectGroup::create($request->safe()->only(['name', 'project_id']));

            $this->syncMembers($group, $request->memberUserIds());

            return response()->json($this->transformForShow($group->load('project', 'users')), 201);
        });
    }

    public function show(ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->load('project', 'users');

        return response()->json($this->transformForShow($projectGroup));
    }

    public function update(UpdateProjectGroupRequest $request, ProjectGroup $projectGroup): JsonResponse
    {
        return DB::transaction(function () use ($request, $projectGroup) {
            $projectGroup->update($request->safe()->only(['name', 'project_id']));

            $this->syncMembers($projectGroup, $request->memberUserIds());

            $projectGroup->load('project', 'users');

            return response()->json($this->transformForShow($projectGroup));
        });
    }

    public function destroy(ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->delete();

        return response()->json(['message' => 'Project group deleted successfully']);
    }

    public function dropdown(): JsonResponse
    {
        $groups = ProjectGroup::orderBy('name')->get()->map(fn (ProjectGroup $group) => [
            'value' => $group->id,
            'label' => $group->name,
        ]);

        return response()->json($groups);
    }

    protected function syncMembers(ProjectGroup $group, ?array $userIds): void
    {
        if ($userIds === null) {
            return;
        }

        $conflictingUserIds = GroupMember::query()
            ->whereIn('user_id', $userIds)
            ->when($group->exists, fn ($query) => $query->where('group_id', '!=', $group->id))
            ->pluck('user_id')
            ->unique();

        if ($conflictingUserIds->isNotEmpty()) {
            $names = User::whereIn('id', $conflictingUserIds)->pluck('name')->implode(', ');

            throw ValidationException::withMessages([
                'member_user_ids' => "These users already belong to another group: {$names}",
            ]);
        }

        $group->users()->sync($userIds);
    }

    protected function transformForIndex(ProjectGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'project_id' => $group->project_id,
            'project_name' => $group->project?->title,
            'member_user_ids' => $group->users->pluck('id')->values()->all(),
            'member_user_names' => $group->users->pluck('name')->implode(', '),
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(ProjectGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'project_id' => $group->project_id,
            'project_name' => $group->project?->title,
            'member_user_ids' => $group->users->pluck('id')->values()->all(),
            'member_user_names' => $group->users->pluck('name')->implode(', '),
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ];
    }
}
