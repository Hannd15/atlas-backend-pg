<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepositoryProject\StoreRepositoryProjectRequest;
use App\Http\Requests\RepositoryProject\UpdateRepositoryProjectRequest;
use App\Models\RepositoryProject;
use App\Services\FileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Repository Projects",
 *     description="Expose research projects available in the institutional repository"
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectPayload",
 *     type="object",
 *     required={"title"},
 *
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=11),
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="url", type="string", format="uri", nullable=true, example="https://repositorio.edu/proyectos/123"),
 *     @OA\Property(property="publish_date", type="string", format="date", nullable=true, example="2024-05-30"),
 *     @OA\Property(property="keywords_es", type="string", nullable=true),
 *     @OA\Property(property="keywords_en", type="string", nullable=true),
 *     @OA\Property(property="abstract_es", type="string", nullable=true),
 *     @OA\Property(property="abstract_en", type="string", nullable=true),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer", example=88)),
 *     @OA\Property(property="files", type="array", @OA\Items(type="string", format="binary"))
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectIndexResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=9),
 *     @OA\Property(property="title", type="string", example="Proyecto de grado"),
 *     @OA\Property(property="authors", type="string", nullable=true, example="Ana Pérez, Juan López"),
 *     @OA\Property(property="advisors", type="string", nullable=true, example="Dr. Gómez, Dra. Ruiz"),
 *     @OA\Property(property="keywords_es", type="string", nullable=true),
 *     @OA\Property(property="thematic_line", type="string", nullable=true, example="IoT"),
 *     @OA\Property(property="publish_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="abstract_es", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=9),
 *     @OA\Property(property="project_id", type="integer", nullable=true, example=11),
 *     @OA\Property(property="title", type="string", example="Proyecto de grado"),
 *     @OA\Property(property="repository_title", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="authors", type="string", nullable=true, example="Ana Pérez, Juan López"),
 *     @OA\Property(property="advisors", type="string", nullable=true, example="Dr. Gómez, Dra. Ruiz"),
 *     @OA\Property(property="keywords_es", type="string", nullable=true),
 *     @OA\Property(property="keywords_en", type="string", nullable=true),
 *     @OA\Property(property="thematic_line", type="string", nullable=true, example="IoT"),
 *     @OA\Property(property="publish_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="abstract_es", type="string", nullable=true),
 *     @OA\Property(property="abstract_en", type="string", nullable=true),
 *     @OA\Property(property="file_ids", type="array", @OA\Items(type="integer")),
 *     @OA\Property(property="file_names", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class RepositoryProjectController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/repository-projects",
     *     summary="List repository projects",
     *     tags={"Repository Projects"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of repository projects",
     *
    *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RepositoryProjectIndexResource"))
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $repositoryProjects = RepositoryProject::withDetails()->orderByDesc('updated_at')->get();

        return response()->json($repositoryProjects->map(fn (RepositoryProject $repositoryProject) => $this->transformForIndex($repositoryProject)));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/repository-projects/{repository_project}",
     *     summary="Show a repository project",
     *     tags={"Repository Projects"},
     *
     *     @OA\Parameter(name="repository_project", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Repository project detail", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=404, description="Repository project not found")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/pg/repository-projects",
     *     summary="Create a repository project",
     *     tags={"Repository Projects"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/RepositoryProjectPayload"))
     *     ),
     *
     *     @OA\Response(response=201, description="Repository project created", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreRepositoryProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $repositoryProject = DB::transaction(function () use ($validated, $request) {
            $attributes = Arr::only($validated, [
                'project_id',
                'title',
                'description',
                'url',
                'publish_date',
                'keywords_es',
                'keywords_en',
                'abstract_es',
                'abstract_en',
            ]);

            $repositoryProject = RepositoryProject::create($attributes);

            $fileIds = collect($validated['file_ids'] ?? [])
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id);

            if ($request->hasFile('files')) {
                $storedFiles = $this->fileStorageService->storeUploadedFiles($request->file('files'));
                $fileIds = $fileIds->merge($storedFiles->pluck('id'));
            }

            if ($fileIds->isNotEmpty()) {
                $repositoryProject->files()->sync($fileIds->unique()->values()->all());
            }

            return $repositoryProject;
        });

        $repositoryProject->loadMissing(
            'files',
            'project.groups.members.user',
            'project.staff.user',
            'project.proposal.thematicLine'
        );

        return response()->json($this->transformForShow($repositoryProject), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/repository-projects/{repository_project}",
     *     summary="Update a repository project",
     *     tags={"Repository Projects"},
     *
     *     @OA\Parameter(name="repository_project", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/RepositoryProjectPayload"))),
     *
     *     @OA\Response(response=200, description="Repository project updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=404, description="Repository project not found")
     * )
     */
    public function update(UpdateRepositoryProjectRequest $request, RepositoryProject $repositoryProject): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($repositoryProject, $validated, $request) {
            $attributes = Arr::only($validated, [
                'title',
                'description',
                'url',
                'publish_date',
                'keywords_es',
                'keywords_en',
                'abstract_es',
                'abstract_en',
            ]);

            if (! empty($attributes)) {
                $repositoryProject->update($attributes);
            }

            $fileIds = null;

            if (array_key_exists('file_ids', $validated)) {
                $fileIds = collect($validated['file_ids'] ?? [])
                    ->filter(fn ($id) => $id !== null)
                    ->map(fn ($id) => (int) $id);
            }

            if ($request->hasFile('files')) {
                $storedFiles = $this->fileStorageService->storeUploadedFiles($request->file('files'));
                $fileIds = ($fileIds ?? $repositoryProject->files->pluck('id'))
                    ->merge($storedFiles->pluck('id'));
            }

            if ($fileIds !== null) {
                $repositoryProject->files()->sync($fileIds->unique()->values()->all());
            }
        });

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

    protected function authorNames(RepositoryProject $repositoryProject): string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $project->loadMissing('groups.members.user');

        $values = $project->groups
            ->flatMap(fn ($group) => $group->members->map(fn ($member) => $member->user?->name))
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }

    protected function advisorNames(RepositoryProject $repositoryProject): string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $project->loadMissing('staff.user');

        $values = $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->map(fn ($staff) => $staff->user?->name)
            ->filter()
            ->unique()
            ->values();

        return $values->isEmpty() ? null : $values->implode(', ');
    }
}
