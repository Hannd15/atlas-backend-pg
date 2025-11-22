<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepositoryProject\StoreRepositoryProjectRequest;
use App\Http\Requests\RepositoryProject\UpdateRepositoryProjectRequest;
use App\Models\Project;
use App\Models\RepositoryProject;
use App\Services\AtlasUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="url", type="string", format="uri", nullable=true, example="https://repositorio.edu/proyectos/123"),
 *     @OA\Property(property="publish_date", type="string", format="date", nullable=true, example="2024-05-30"),
 *     @OA\Property(property="keywords_es", type="string", nullable=true),
 *     @OA\Property(property="keywords_en", type="string", nullable=true),
 *     @OA\Property(property="abstract_es", type="string", nullable=true),
 *     @OA\Property(property="abstract_en", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectStorePayload",
 *     type="object",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/RepositoryProjectPayload"),
 *         @OA\Schema(
 *             required={"project_id"},
 *
 *             @OA\Property(property="project_id", type="integer", example=1)
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="RepositoryProjectUpdatePayload",
 *     type="object",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/RepositoryProjectPayload")
 *     }
 * )
 * @OA\Schema(
 *     schema="RepositoryProjectIndexResource",
 *     type="object",
 *
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
 *     @OA\Property(property="title", type="string", example="Proyecto de grado"),
 *     @OA\Property(property="repository_title", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="authors", type="string", nullable=true, example="Ana Pérez, Juan López"),
 *     @OA\Property(property="advisors", type="string", nullable=true, example="Dr. Gómez, Dra. Ruiz"),
 *     @OA\Property(property="keywords_es", type="string", nullable=true),
 *     @OA\Property(property="keywords_en", type="string", nullable=true),
 *     @OA\Property(property="thematic_line", type="string", nullable=true, example="IoT"),
 *     @OA\Property(property="publish_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="abstract_es", type="string", nullable=true),
 *     @OA\Property(property="abstract_en", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class RepositoryProjectController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/repository-projects",
     *     summary="List repository projects",
     *     tags={"Repository Projects"},
     *
     *     @OA\Parameter(name="year", in="query", description="Filter by specific year", @OA\Schema(type="integer", example=2025)),
     *     @OA\Parameter(name="year_from", in="query", description="Filter by year range (start)", @OA\Schema(type="integer", example=2024)),
     *     @OA\Parameter(name="year_to", in="query", description="Filter by year range (end)", @OA\Schema(type="integer", example=2025)),
     *     @OA\Parameter(
     *         name="thematic_line_ids[]",
     *         in="query",
     *         description="Filter by one or more thematic line identifiers",
     *
     *         @OA\Schema(type="array", @OA\Items(type="integer")),
     *         style="form",
     *         explode=true
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of repository projects",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RepositoryProjectIndexResource"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = RepositoryProject::withDetails();

        // Filter by specific year or year range
        if ($request->has('year')) {
            $year = (int) $request->input('year');
            $query->whereYear('publish_date', $year);
        } elseif ($request->has('year_from') || $request->has('year_to')) {
            if ($request->has('year_from')) {
                $yearFrom = (int) $request->input('year_from');
                $query->whereYear('publish_date', '>=', $yearFrom);
            }
            if ($request->has('year_to')) {
                $yearTo = (int) $request->input('year_to');
                $query->whereYear('publish_date', '<=', $yearTo);
            }
        }

        // Filter by thematic lines
        $thematicLineIds = $this->normalizeQueryIds($request->input('thematic_line_ids'));

        if (! empty($thematicLineIds)) {
            $query->whereHas('project.proposal', function ($q) use ($thematicLineIds) {
                $q->whereIn('thematic_line_id', $thematicLineIds);
            });
        } elseif ($request->filled('thematic_line_id')) {
            $thematicLineId = (int) $request->input('thematic_line_id');

            $query->whereHas('project.proposal', function ($q) use ($thematicLineId) {
                $q->where('thematic_line_id', $thematicLineId);
            });
        }

        $repositoryProjects = $query->orderByDesc('updated_at')->get();

        if ($repositoryProjects->isEmpty()) {
            return response()->json([]);
        }

        $token = trim((string) $request->bearerToken());
        $userIds = $this->collectRepositoryUserIds($repositoryProjects);
        $userNames = empty($userIds)
            ? []
            : $this->userNamesForIds($userIds, $token);

        return response()->json($repositoryProjects->map(fn (RepositoryProject $repositoryProject) => $this->transformForIndex($repositoryProject, $userNames)));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/repository-projects/{repositoryProject}",
     *     summary="Show a repository project",
     *     tags={"Repository Projects"},
     *
     *     @OA\Parameter(name="repositoryProject", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Repository project detail", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=404, description="Repository project not found")
     * )
     */
    public function show(Request $request, RepositoryProject $repositoryProject): JsonResponse
    {
        $repositoryProject->loadMissing(
            'project.groups.members',
            'project.staff',
            'project.proposal.thematicLine'
        );

        $userNames = $this->resolveRepositoryUserNames($repositoryProject, trim((string) $request->bearerToken()));

        return response()->json($this->transformForShow($repositoryProject, $userNames));
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
     *         @OA\JsonContent(ref="#/components/schemas/RepositoryProjectStorePayload")
     *     ),
     *
     *     @OA\Response(response=201, description="Repository project created", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreRepositoryProjectRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        $repositoryProject->loadMissing(
            'project.groups.members',
            'project.staff',
            'project.proposal.thematicLine'
        );

        $userNames = $this->resolveRepositoryUserNames($repositoryProject, trim((string) $request->bearerToken()));

        return response()->json($this->transformForShow($repositoryProject, $userNames), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/repository-projects/{repositoryProject}",
     *     summary="Update a repository project",
     *     tags={"Repository Projects"},
     *
     *     @OA\Parameter(name="repositoryProject", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/RepositoryProjectUpdatePayload")),
     *
     *     @OA\Response(response=200, description="Repository project updated", @OA\JsonContent(ref="#/components/schemas/RepositoryProjectResource")),
     *     @OA\Response(response=404, description="Repository project not found")
     * )
     */
    public function update(UpdateRepositoryProjectRequest $request, RepositoryProject $repositoryProject): JsonResponse
    {
        $validated = $request->validated();

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

        $repositoryProject->loadMissing(
            'project.groups.members',
            'project.staff',
            'project.proposal.thematicLine'
        );

        $userNames = $this->resolveRepositoryUserNames($repositoryProject, trim((string) $request->bearerToken()));

        return response()->json($this->transformForShow($repositoryProject, $userNames));
    }

    protected function transformForIndex(RepositoryProject $repositoryProject, array $userNames): array
    {
        return [
            'id' => $repositoryProject->id,
            'title' => $repositoryProject->project?->title ?? $repositoryProject->title,
            'authors' => $this->authorNames($repositoryProject, $userNames),
            'advisors' => $this->advisorNames($repositoryProject, $userNames),
            'keywords_es' => $repositoryProject->keywords_es,
            'thematic_line' => $repositoryProject->project?->thematicLine?->name,
            'publish_date' => optional($repositoryProject->publish_date)->toDateString(),
            'abstract_es' => $repositoryProject->abstract_es,
            'created_at' => optional($repositoryProject->created_at)->toDateTimeString(),
            'updated_at' => optional($repositoryProject->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(RepositoryProject $repositoryProject, array $userNames): array
    {
        return array_merge(
            $this->transformForIndex($repositoryProject, $userNames),
            [
                'repository_title' => $repositoryProject->title,
                'project_id' => $repositoryProject->project_id,
                'description' => $repositoryProject->description,
                'url' => $repositoryProject->url,
                'keywords_en' => $repositoryProject->keywords_en,
                'abstract_en' => $repositoryProject->abstract_en,
            ]
        );
    }

    protected function authorNames(RepositoryProject $repositoryProject, array $userNames): ?string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $memberIds = $project->groups
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        return $this->implodeUserNames($memberIds, $userNames);
    }

    protected function advisorNames(RepositoryProject $repositoryProject, array $userNames): ?string
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return '';
        }

        $staffIds = $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        return $this->implodeUserNames($staffIds, $userNames);
    }

    /**
     * @param  array<int, mixed>|int|string|null  $value
     * @return array<int, int>
     */
    protected function normalizeQueryIds($value): array
    {
        if ($value === null) {
            return [];
        }

        return collect(Arr::wrap($value))
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return $item;
                }

                if (is_string($item)) {
                    return preg_split('/[\s,]+/', $item, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                }

                return [$item];
            })
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<RepositoryProject>  $repositoryProjects
     * @return array<int, int>
     */
    protected function collectRepositoryUserIds(iterable $repositoryProjects): array
    {
        $ids = [];

        foreach ($repositoryProjects as $repositoryProject) {
            $ids = array_merge($ids, $this->repositoryProjectUserIds($repositoryProject));
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<int, int>
     */
    protected function repositoryProjectUserIds(RepositoryProject $repositoryProject): array
    {
        $project = $repositoryProject->project;

        if (! $project) {
            return [];
        }

        $memberIds = $project->groups
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $staffIds = $project->staff
            ->filter(fn ($staff) => $staff->status === null || $staff->status === 'active')
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return array_merge($memberIds, $staffIds);
    }

    protected function resolveRepositoryUserNames(RepositoryProject $repositoryProject, string $token): array
    {
        $ids = $this->repositoryProjectUserIds($repositoryProject);

        if (empty($ids)) {
            return [];
        }

        return $this->userNamesForIds($ids, $token);
    }

    protected function implodeUserNames(iterable $userIds, array $userNames): ?string
    {
        $names = collect($userIds)
            ->map(fn ($id) => $userNames[(int) $id] ?? "User #{$id}")
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? null : $names->implode(', ');
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
