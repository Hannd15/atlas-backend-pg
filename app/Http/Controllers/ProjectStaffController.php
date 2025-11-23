<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Tag(
 *     name="Project Staff",
 *     description="Manage staff assignments for projects"
 * )
 *
 * @OA\Schema(
 *     schema="ProjectStaffAssignment",
 *     type="object",
 *
 *     @OA\Property(property="position", type="string", example="Director"),
 *     @OA\Property(property="user_name", type="string", example="Ana Directora")
 * )
 */
class ProjectStaffController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/projects/{project}/staff",
     *     summary="List staff assigned to a project",
     *     tags={"Project Staff"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project staff assignments",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProjectStaffAssignment"))
     *     )
     * )
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $assignments = $project->staff()->with('position')->orderBy('project_position_id')->get();

        if ($assignments->isEmpty()) {
            return response()->json([]);
        }

        $userIds = $assignments
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $token = trim((string) $request->bearerToken());
        $userNames = $token === '' || empty($userIds)
            ? []
            : $this->atlasUserService->namesByIds($token, $userIds);

        $payload = $assignments->map(function (ProjectStaff $assignment) use ($userNames) {
            return $this->formatAssignment(
                $assignment->position?->name,
                $userNames[$assignment->user_id] ?? $assignment->user?->name ?? "User #{$assignment->user_id}"
            );
        })->values();

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/projects/{project}/project-positions/{project_position}/users/{user}/staff",
     *     summary="Assign a user to a project position",
     *     tags={"Project Staff"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project_position", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Assignment created", @OA\JsonContent(ref="#/components/schemas/ProjectStaffAssignment")),
     *     @OA\Response(response=200, description="Assignment already existed", @OA\JsonContent(ref="#/components/schemas/ProjectStaffAssignment"))
     * )
     */
    public function store(Request $request, Project $project, ProjectPosition $projectPosition, User $user): JsonResponse
    {
        $assignment = ProjectStaff::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'project_position_id' => $projectPosition->id,
                'user_id' => $user->id,
            ],
            [
                'status' => 'active',
            ]
        );

        $userName = $this->resolveUserName($request, $user);
        $payload = $this->formatAssignment($projectPosition->name, $userName);

        $status = $assignment->wasRecentlyCreated ? 201 : 200;

        return response()->json($payload, $status);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/projects/{project}/project-positions/{project_position}/users/{user}/staff",
     *     summary="Remove a staff assignment",
     *     tags={"Project Staff"},
     *
     *     @OA\Parameter(name="project", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="project_position", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="user", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=204, description="Assignment removed"),
     *     @OA\Response(response=404, description="Assignment not found")
     * )
     */
    public function destroy(Project $project, ProjectPosition $projectPosition, User $user): Response
    {
        $deleted = ProjectStaff::query()
            ->where('project_id', $project->id)
            ->where('project_position_id', $projectPosition->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted === 0) {
            abort(404, 'Staff assignment not found.');
        }

        return response()->noContent();
    }

    protected function formatAssignment(?string $positionName, string $userName): array
    {
        return [
            'position' => $positionName ?? 'Sin posición',
            'user_name' => $userName,
        ];
    }

    protected function resolveUserName(Request $request, User $user): string
    {
        $token = trim((string) $request->bearerToken());

        if ($token === '') {
            return $user->name ?? "User #{$user->id}";
        }

        $names = $this->atlasUserService->namesByIds($token, [$user->id]);

        return $names[$user->id] ?? $user->name ?? "User #{$user->id}";
    }
}
