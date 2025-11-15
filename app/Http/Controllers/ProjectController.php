<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Deliverable;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectStaff;
use App\Models\ProjectStatus;
use Illuminate\Http\JsonResponse;

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
 *
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="title", type="string", example="Plataforma IoT"),
 *     @OA\Property(property="status", type="string", example="Activo"),
 *     @OA\Property(property="proposal_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="thematic_line_name", type="string", nullable=true, example="Inteligencia Artificial"),
 *     @OA\Property(property="member_names", type="string", example="Laura Proposer, Juan Developer"),
 *     @OA\Property(property="staff", type="array", @OA\Items(ref="#/components/schemas/ProjectStaffResource")),
 *     @OA\Property(property="meetings", type="array", @OA\Items(ref="#/components/schemas/ProjectMeetingResource")),
 *     @OA\Property(property="deliverables", type="array", @OA\Items(ref="#/components/schemas/ProjectDeliverableResource")),
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
 *     @OA\Property(property="description", type="string", example="Documento inicial"),
 *     @OA\Property(property="due_date", type="string", format="date-time", example="2025-04-30 18:00:00"),
 *     @OA\Property(property="status", type="string", enum={"pending", "submitted"}, example="pending"),
 *     @OA\Property(property="submission", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=44),
 *         @OA\Property(property="submitted_at", type="string", format="date-time", example="2025-04-28 18:00:00")
 *     )
 * )
 */
class ProjectController extends Controller
{
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
    public function index(): JsonResponse
    {
        $projects = Project::with(['groups.members.user', 'status'])->orderByDesc('updated_at')->get();

        return response()->json($projects->map(fn (Project $project) => $this->formatProjectForIndex($project)));
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

        return response()->json($this->formatProject($project->load($this->projectRelations())), 201);
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
    public function show(Project $project): JsonResponse
    {
        $project->load($this->projectRelations());

        return response()->json($this->formatProject($project));
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

        return response()->json($this->formatProject($project));
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

    protected function formatProjectForIndex(Project $project): array
    {
        $project->loadMissing('groups.members.user', 'status');

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'member_names' => $this->memberNames($project),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function projectRelations(): array
    {
        return [
            'proposal.thematicLine',
            'groups.members.user',
            'status',
            'staff.user',
            'staff.position',
            'deliverables.phase',
            'meetings',
            'submissions',
        ];
    }

    protected function formatProject(Project $project): array
    {
        $project->loadMissing($this->projectRelations());

        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status?->name,
            'proposal_id' => $project->proposal_id,
            'thematic_line_name' => $project->proposal?->thematicLine?->name,
            'member_names' => $this->memberNames($project),
            'staff' => $this->transformStaff($project),
            'meetings' => $this->transformMeetings($project),
            'deliverables' => $this->transformDeliverables($project),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformStaff(Project $project): array
    {
        return $project->staff
            ->map(fn (ProjectStaff $staff) => [
                'user_name' => $staff->user?->name,
                'position_name' => $staff->position?->name,
            ])
            ->values()
            ->all();
    }

    protected function transformMeetings(Project $project): array
    {
        return $project->meetings
            ->sortByDesc('meeting_date')
            ->values()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'meeting_date' => optional($meeting->meeting_date)->toDateString(),
                'observations' => $meeting->observations,
                'url' => $meeting->url,
                'created_by' => $meeting->created_by,
            ])
            ->all();
    }

    protected function transformDeliverables(Project $project): array
    {
        $submissionsByDeliverable = $project->submissions->groupBy('deliverable_id');

        return $project->deliverables
            ->sortBy('due_date')
            ->values()
            ->map(function (Deliverable $deliverable) use ($submissionsByDeliverable) {
                $submissionGroup = $submissionsByDeliverable->get($deliverable->id);
                $submission = $submissionGroup ? $submissionGroup->sortByDesc('submission_date')->first() : null;

                return [
                    'id' => $deliverable->id,
                    'name' => $deliverable->name,
                    'description' => $deliverable->description,
                    'due_date' => optional($deliverable->due_date)->toDateTimeString(),
                    'status' => $submission ? 'submitted' : 'pending',
                    'submission' => $submission ? [
                        'id' => $submission->id,
                        'submitted_at' => optional($submission->submission_date)->toDateTimeString(),
                    ] : null,
                ];
            })
            ->all();
    }

    protected function memberNames(Project $project): string
    {
        $project->loadMissing('groups.members.user');

        return $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');
    }
}
