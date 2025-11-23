<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalActionKey;
use App\Http\Requests\ProjectGroup\StoreProjectGroupRequest;
use App\Http\Requests\ProjectGroup\UpdateProjectGroupRequest;
use App\Models\ApprovalRequest;
use App\Models\ProjectGroup;
use App\Services\ApprovalRequestService;
use App\Services\AtlasAuthService;
use App\Services\AtlasUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Project Groups",
 *     description="Manage project groups and their members"
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupPayload",
 *     type="object",
 *
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25))
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupMembersPayload",
 *     type="object",
 *     required={"member_user_ids"},
 *
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25))
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupResource",
 *     type="object",
 *     description="Minimal project group representation. Members accessible via /project-groups/{id}/members endpoint.",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25)),
 *     @OA\Property(property="project_name", type="string", nullable=true, example="Sistema IoT"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupIndexResource",
 *     type="object",
 *     description="Extended project group list representation including member information.",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="project_name", type="string", nullable=true, example="Sistema IoT"),
 *     @OA\Property(property="phase_name", type="string", nullable=true, example="Fase 1"),
 *     @OA\Property(property="period_name", type="string", nullable=true, example="2024-1"),
 *     @OA\Property(property="member_user_names", type="string", example="Ana López, Juan Pérez"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectGroupShowResource",
 *     type="object",
 *     description="Project group details matching the index representation while omitting reserved member names.",
 *
 *     @OA\Property(property="id", type="integer", example=3),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=7),
 *     @OA\Property(property="project_name", type="string", nullable=true, example="Sistema IoT"),
 *     @OA\Property(property="member_user_ids", type="array", @OA\Items(type="integer", example=25)),
 *     @OA\Property(property="phase_name", type="string", nullable=true, example="Fase 1"),
 *     @OA\Property(property="period_name", type="string", nullable=true, example="2024-1"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProjectGroupController extends AtlasAuthenticatedController
{
    public function __construct(
        AtlasAuthService $atlasAuthService,
        protected AtlasUserService $atlasUserService,
        protected ApprovalRequestService $approvalRequestService
    ) {
        parent::__construct($atlasAuthService);
    }

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
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProjectGroupIndexResource"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $groups = ProjectGroup::with('project.phase.period', 'members')->orderByDesc('updated_at')->get();

        if ($groups->isEmpty()) {
            return response()->json([]);
        }

        $token = trim((string) $request->bearerToken());
        $memberIds = $groups
            ->flatMap(fn (ProjectGroup $group) => $group->members->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $userNames = empty($memberIds)
            ? []
            : $this->userNamesForIds($memberIds, $token);

        return response()->json($groups->map(fn (ProjectGroup $group) => $this->transformForIndex($group, $userNames)));
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
        $requestedBy = $this->resolveAuthenticatedUserId($request);

        return DB::transaction(function () use ($request, $requestedBy) {
            $group = ProjectGroup::create($request->safe()->only(['project_id']));

            $this->syncMembers($group, $request->memberUserIds(), $requestedBy);

            $group->loadMissing('project', 'members');

            return response()->json($this->transformForShow($group), 201);
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
     *     @OA\Response(
     *         response=200,
     *         description="Project group details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ProjectGroupShowResource")
     *     ),
     *
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function show(Request $request, ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->loadMissing('project.phase.period', 'members');

        $token = trim((string) $request->bearerToken());
        $memberIds = $projectGroup->members
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $userNames = empty($memberIds)
            ? []
            : $this->userNamesForIds($memberIds, $token);

        $data = $this->transformForIndex($projectGroup, $userNames);
        unset($data['member_user_names']);

        return response()->json($data);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/project-groups/{project_group}",
     *     summary="Update a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\Parameter(name="project_group", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/ProjectGroupMembersPayload")),
     *
     *     @OA\Response(response=200, description="Project group updated", @OA\JsonContent(ref="#/components/schemas/ProjectGroupResource")),
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function update(UpdateProjectGroupRequest $request, ProjectGroup $projectGroup): JsonResponse
    {
        $requestedBy = $this->resolveAuthenticatedUserId($request);

        return DB::transaction(function () use ($request, $projectGroup, $requestedBy) {
            $this->syncMembers($projectGroup, $request->memberUserIds(), $requestedBy);

            $projectGroup->loadMissing('project', 'members');

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
     *                 @OA\Property(property="label", type="string", example="Sistema IoT")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $groups = ProjectGroup::with('project')->orderBy('id')->get()->map(fn (ProjectGroup $group) => [
            'value' => $group->id,
            'label' => $group->project?->title ?? "Project Group {$group->id}",
        ])->values();

        return response()->json($groups);
    }

    protected function syncMembers(ProjectGroup $group, ?array $userIds, int $requestedBy): void
    {
        if ($userIds === null) {
            return;
        }

        $currentMembers = $group->users()
            ->pluck('users.id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $desiredMembers = collect($userIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $toRemove = array_values(array_diff($currentMembers, $desiredMembers));
        $toAdd = array_values(array_diff($desiredMembers, $currentMembers));

        if (! empty($toRemove)) {
            $group->users()->detach($toRemove);
        }

        foreach ($toAdd as $userId) {
            $this->dispatchMemberAdditionRequest($group, $requestedBy, $userId);
        }
    }

    protected function dispatchMemberAdditionRequest(ProjectGroup $group, int $requestedBy, int $userId): void
    {
        if ($this->hasPendingMembershipRequest($group->id, $userId)) {
            return;
        }

        $group->loadMissing('project', 'users');
        $groupLabel = $group->project?->title ?? "Grupo {$group->id}";

        $memberNames = $group->users
            ->pluck('name')
            ->filter(fn (?string $name) => $name !== null && $name !== '')
            ->unique()
            ->values()
            ->all();

        $membersDescription = $memberNames === []
            ? 'Sin miembros actuales.'
            : 'Miembros actuales: '.implode(', ', $memberNames).'.';

        $payload = [
            'title' => "Invitación para unirse a {$groupLabel}",
            'description' => "Confirma si deseas unirte a este grupo de proyecto. {$membersDescription}",
            'requested_by' => $requestedBy,
            'action_key' => ApprovalActionKey::ProjectGroupAddMember->value,
            'action_payload' => [
                'group_id' => $group->id,
                'user_id' => $userId,
            ],
            'status' => ApprovalRequest::STATUS_PENDING,
        ];

        $this->approvalRequestService->create($payload, [$userId]);
    }

    protected function hasPendingMembershipRequest(int $groupId, int $userId): bool
    {
        return ApprovalRequest::query()
            ->where('action_key', ApprovalActionKey::ProjectGroupAddMember->value)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->where('action_payload->group_id', $groupId)
            ->where('action_payload->user_id', $userId)
            ->exists();
    }

    protected function transformForIndex(ProjectGroup $group, array $userNames): array
    {
        $memberIds = $group->members->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $group->id,
            'project_id' => $group->project_id,
            'project_name' => $group->project?->title,
            'phase_name' => $group->project?->phase?->name,
            'period_name' => $group->project?->phase?->period?->name,
            'member_user_ids' => $memberIds,
            'member_user_names' => $this->implodeUserNames($memberIds, $userNames),
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(ProjectGroup $group): array
    {
        $memberIds = $group->members->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $group->id,
            'project_id' => $group->project_id,
            'project_name' => $group->project?->title,
            'member_user_ids' => $memberIds,
            'created_at' => optional($group->created_at)->toDateTimeString(),
            'updated_at' => optional($group->updated_at)->toDateTimeString(),
        ];
    }

    protected function implodeUserNames(array $userIds, array $userNames): string
    {
        return collect($userIds)
            ->map(fn ($id) => $userNames[$id] ?? "User #{$id}")
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    protected function userNamesForIds(array $ids, string $token): array
    {
        $ids = collect($ids)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return [];
        }

        return $this->atlasUserService->namesByIds($token, $ids);
    }
}
