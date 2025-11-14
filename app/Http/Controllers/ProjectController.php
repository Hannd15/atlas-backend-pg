<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
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
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="proposal_id", type="integer", nullable=true, example=5),
 *     @OA\Property(property="group_ids", type="array", @OA\Items(type="integer", example=7)),
 *     @OA\Property(property="member_names", type="array", @OA\Items(type="string", example="Laura Proposer")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
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
        $projects = Project::with('proposal', 'groups.members.user')->orderByDesc('updated_at')->get();

        return response()->json($projects->map(fn (Project $project) => $this->transformForIndex($project)));
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
     *             required={"title","status"},
     *
     *             @OA\Property(property="title", type="string", example="Nuevo proyecto"),
     *             @OA\Property(property="status", type="string", example="draft"),
     *             @OA\Property(property="proposal_id", type="integer", nullable=true, example=8)
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Project created successfully", @OA\JsonContent(ref="#/components/schemas/ProjectResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->safe()->only(['title', 'status', 'proposal_id']));

        return response()->json($this->transformForShow($project->load('proposal', 'groups.members.user')), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/projects/{id}",
     *     summary="Get a specific project",
     *     tags={"Projects"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Project details", @OA\JsonContent(ref="#/components/schemas/ProjectResource")),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function show(Project $project): JsonResponse
    {
        $project->load('proposal', 'groups.members.user');

        return response()->json($this->transformForShow($project));
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
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="proposal_id", type="integer", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Project updated successfully", @OA\JsonContent(ref="#/components/schemas/ProjectResource")),
     *     @OA\Response(response=404, description="Project not found")
     * )
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->safe()->only(['title', 'status', 'proposal_id']));

        $project->load('proposal', 'groups.members.user');

        return response()->json($this->transformForShow($project));
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

    protected function transformForIndex(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'status' => $project->status,
            'proposal_id' => $project->proposal_id,
            'group_ids' => $project->groups->pluck('id')->values()->all(),
            'member_names' => $this->memberNames($project),
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
            'member_names' => $this->memberNames($project),
            'created_at' => optional($project->created_at)->toDateTimeString(),
            'updated_at' => optional($project->updated_at)->toDateTimeString(),
        ];
    }

    protected function memberNames(Project $project): array
    {
        return $project->groups
            ->flatMap(function ($group) {
                return $group->members->map(fn ($member) => $member->user?->name);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
