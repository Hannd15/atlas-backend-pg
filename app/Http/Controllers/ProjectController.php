<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Services\AtlasUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Projects",
 *     description="API endpoints for managing projects and their groups"
 * )
 *
 * @OA\Schema(
 *     schema="ProjectResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="title", type="string", example="Plataforma IoT"),
 *     @OA\Property(property="status", type="string", example="Activo"),
 *     @OA\Property(property="member_names", type="string", example="Laura Proposer, Juan Developer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectDetailResource",
 *     type="object",
 *     description="Minimal project representation; related entities available via dedicated endpoints.",
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="title", type="string", example="Plataforma IoT"),
 *     @OA\Property(property="status", type="string", example="Activo"),
 *     @OA\Property(property="proposal_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="thematic_line_name", type="string", nullable=true, example="Inteligencia Artificial"),
 *     @OA\Property(property="member_names", type="string", example="Laura Proposer, Juan Developer"),
 *     @OA\Property(property="staff_names", type="string", example="Ana Directora, Luis Asistente"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectStaffResource",
 *     type="object",
 *
 *     @OA\Property(property="user_name", type="string", example="Ana Directora"),
 *     @OA\Property(property="position_name", type="string", example="Director")
 * )
 *
 * @OA\Schema(
 *     schema="ProjectMeetingResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="meeting_date", type="string", format="date", example="2025-04-15"),
 *     @OA\Property(property="observations", type="string", example="Initial kickoff meeting"),
 *     @OA\Property(property="url", type="string", example="https://meetings.test/project-1/20250415"),
 *     @OA\Property(property="created_by", type="integer", example=5)
 * )
 *
 * @OA\Schema(
 *     schema="ProjectDeliverableResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Plan de trabajo"),
 *     @OA\Property(property="due_date", type="string", format="date-time", example="2025-04-30 18:00:00"),
 *     @OA\Property(property="grading", type="number", format="float", nullable=true, example=4.3),
 *     @OA\Property(
 *         property="state",
 *         type="string",
 *         enum={"Pendiente de entrega", "Atrasado", "Pendiente por revisión", "Al día"},
 *         example="Pendiente de entrega"
 *     )
 * )
 */
class ProjectController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/projects",
     *     summary="Get all projects",
     *     tags={"Projects"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of projects with related group/member metadata",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProjectResource"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $projects = Project::with(['groups.members', 'status'])->orderByDesc('updated_at')->get();

        if ($projects->isEmpty()) {
            return response()->json([]);
        }

        $token = trim((string) $request->bearerToken());
        $userIds = $this->collectProjectUserIds($projects);
        $userNames = empty($userIds)
            ? []
            : $this->userNamesByIds($userIds, $token);

        return response()->json($projects->map(fn (Project $project) => $this->formatProjectForIndex($project, $userNames)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/projects",
     *     summary="Create a new project",
     *     tags={"Projects"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title"},

     *
     *             @OA\Property(property="title", type="string", example="Nuevo proyecto"),
     *             @OA\Property(property="proposal_id", type="integer", nullable=true, example=8)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Project created successfully", @OA\JsonContent(ref="#/components/schemas/ProjectDetailResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['title', 'proposal_id']);

        $activoStatusId = ProjectStatus::firstOrCreate(['name' => 'Activo'])->id;

        $project = Project::create($data + ['status_id' => $activoStatusId]);
        $project->load($this->projectRelations());

        $userNames = $this->resolveProjectUserNames($project, trim((string) $request->bearerToken()));

        return response()->json($this->formatProject($project, $userNames), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/projects/{id}",
     *     summary="Get a specific project",
     *     tags={"Projects"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Project details", @OA\JsonContent(ref="#/components/schemas/ProjectDetailResource")),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $project->load($this->projectRelations());

        $userNames = $this->resolveProjectUserNames($project, trim((string) $request->bearerToken()));

        return response()->json($this->formatProject($project, $userNames));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/projects/{id}",
     *     summary="Update a project",
     *     tags={"Projects"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="status_id", type="integer", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Project updated successfully", @OA\JsonContent(ref="#/components/schemas/ProjectDetailResource")),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->safe()->only(['title', 'status_id']));

        $project->load($this->projectRelations());

        $userNames = $this->resolveProjectUserNames($project, trim((string) $request->bearerToken()));

        return response()->json($this->formatProject($project, $userNames));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/projects/{id}",
     *     summary="Delete a project",
     *     tags={"Projects"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Project deleted successfully"),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/projects/dropdown",
     *     summary="Get projects for dropdowns",
     *     tags={"Projects"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of projects formatted for dropdowns",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=3),
     *                 @OA\Property(property="label", type="string", example="Sistema de monitoreo")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $projects = Project::orderBy('title')->get()->map(fn (Project $project) => [
            'value' => $project->id,
            'label' => $project->title,
        ]);

        return response()->json($projects);
    }

    protected function formatProjectForIndex(Project $project, array $userNames): array
    {
        $project->loadMissing('groups.members', 'status');

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'member_names' => $this->memberNames($project, $userNames),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function projectRelations(): array
    {
        return [
            'proposal.thematicLine',
            'groups.members',
            'status',
            'staff.position',
            'deliverables',
            'meetings',
        ];
    }

    protected function formatProject(Project $project, array $userNames = []): array
    {
        $project->loadMissing($this->projectRelations());

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'proposal_id' => $project->proposal_id,
            'thematic_line_name' => $project->proposal?->thematicLine?->name,
            'member_names' => $this->memberNames($project, $userNames),
            'staff_names' => $project->staff->map(fn ($s) => $s->position?->name)->filter()->unique()->implode(', '),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function memberNames(Project $project, array $userNames): string
    {
        $project->loadMissing('groups.members');

        return $project->groups
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->map(fn ($id) => $userNames[$id] ?? "User #{$id}")
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
    }

    /**
     * @param  iterable<Project>  $projects
     * @return array<int, int>
     */
    protected function collectProjectUserIds(iterable $projects): array
    {
        $ids = [];

        foreach ($projects as $project) {
            $ids = array_merge($ids, $this->projectUserIds($project));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    protected function projectUserIds(Project $project): array
    {
        $memberIds = $project->groups
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $staffIds = $project->staff
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_merge($memberIds, $staffIds);
    }

    protected function resolveProjectUserNames(Project $project, string $token): array
    {
        $ids = $this->projectUserIds($project);

        if (empty($ids)) {
            return [];
        }

        return $this->userNamesByIds($ids, $token);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    protected function userNamesByIds(array $ids, string $token): array
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
