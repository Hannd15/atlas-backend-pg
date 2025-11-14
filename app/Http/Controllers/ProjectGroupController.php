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

/**
 * @OA\Tag(
 *     name="Project Groups",
 *     description="Manage project groups and their members"
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupPayload",
 *     type="object",
 *     required={"name"},
 *
 *     @OA\Property(property="name", type="string", example="Grupo Alfa"),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25))
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="Grupo Alfa"),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="project_name", type="string", nullable=true, example="Sistema IoT"),
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25)),
 *     @OA\Property(property="member_user_names", type="string", example="Ana López, Juan Pérez"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProjectGroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/project-groups",
     *     summary="List project groups",
     *     tags={"Project Groups"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of project groups",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProjectGroupResource"))
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $groups = ProjectGroup::with('project', 'users')->orderByDesc('updated_at')->get();

        return response()->json($groups->map(fn (ProjectGroup $group) => $this->transformForIndex($group)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/project-groups",
     *     summary="Create a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/ProjectGroupPayload")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Project group created",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ProjectGroupResource")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectGroupRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $group = ProjectGroup::create($request->safe()->only(['name', 'project_id']));

            $this->syncMembers($group, $request->memberUserIds());

            return response()->json($this->transformForShow($group->load('project', 'users')), 201);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/pg/project-groups/{project_group}",
     *     summary="Show a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\Parameter(name="project_group", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Project group details", @OA\JsonContent(ref="#/components/schemas/ProjectGroupResource")),
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function show(ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->load('project', 'users');

        return response()->json($this->transformForShow($projectGroup));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/project-groups/{project_group}",
     *     summary="Update a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\Parameter(name="project_group", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/ProjectGroupPayload")),
     *
     *     @OA\Response(response=200, description="Project group updated", @OA\JsonContent(ref="#/components/schemas/ProjectGroupResource")),
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function update(UpdateProjectGroupRequest $request, ProjectGroup $projectGroup): JsonResponse
    {
        return DB::transaction(function () use ($request, $projectGroup) {
            $projectGroup->update($request->safe()->only(['name', 'project_id']));

            $this->syncMembers($projectGroup, $request->memberUserIds());

            $projectGroup->load('project', 'users');

            return response()->json($this->transformForShow($projectGroup));
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/project-groups/{project_group}",
     *     summary="Delete a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\Parameter(name="project_group", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Project group deleted"),
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function destroy(ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->delete();

        return response()->json(['message' => 'Project group deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/project-groups/dropdown",
     *     summary="Project groups dropdown",
     *     tags={"Project Groups"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pairs of value/label",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=3),
     *                 @OA\Property(property="label", type="string", example="Grupo Alfa")
     *             )
     *         )
     *     )
     * )
     */
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
