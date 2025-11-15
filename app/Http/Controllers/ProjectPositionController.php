<?php

namespace App\Http\Controllers;

use App\Models\ProjectPosition;
use App\Services\AtlasUserService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Project Positions",
 *     description="API endpoints for managing project positions"
 * )
 */
class ProjectPositionController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/project-positions",
     *     summary="Get all project positions",
     *     tags={"Project Positions"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of project positions with eligible user names and staff names"
     *     )
     * )
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $positions = ProjectPosition::with(['eligibleUsers', 'staff'])->orderBy('updated_at', 'desc')->get();

        if ($positions->isEmpty()) {
            return response()->json($positions);
        }

        $token = trim((string) $request->bearerToken());
        $userIds = $positions
            ->flatMap(fn (ProjectPosition $position) => $position->eligibleUsers->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $userNames = empty($userIds)
            ? []
            : $this->userNamesForIds($userIds, $token);

        $positions->each(function (ProjectPosition $position) use ($userNames) {
            $ids = $position->eligibleUsers->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();

            $position->eligible_user_names = $ids
                ->map(fn ($id) => $userNames[$id] ?? "User #{$id}")
                ->filter()
                ->unique()
                ->implode(', ');

            $position->staff_names = $position->staff->map(fn ($staff) => "Staff #{$staff->id}")->implode(', ');
        });

        return response()->json($positions);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/project-positions",
     *     summary="Create a new project position",
     *     tags={"Project Positions"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", example="Jurado")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Project position created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:project_positions,name',
        ]);

        $position = ProjectPosition::create($request->only('name'));

        return response()->json($position, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/project-positions/{id}",
     *     summary="Get a specific project position",
     *     tags={"Project Positions"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project position details with relation IDs"
     *     )
     * )
     */
    public function show(ProjectPosition $projectPosition): \Illuminate\Http\JsonResponse
    {
        $projectPosition->load('eligibleUsers', 'staff');

        $projectPosition->eligible_user_ids = $projectPosition->eligibleUsers->pluck('id');
        $projectPosition->staff_ids = $projectPosition->staff->pluck('id');

        return response()->json($projectPosition);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/project-positions/{id}",
     *     summary="Update a project position",
     *     tags={"Project Positions"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(
     *                 property="eligible_user_ids",
     *                 type="array",
     *                 description="Array of user IDs eligible for this position. Empty array clears all eligibilities. Null or missing field leaves current assignments untouched.",
     *
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project position updated successfully"
     *     )
     * )
     */
    public function update(Request $request, ProjectPosition $projectPosition): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:project_positions,name,'.$projectPosition->id,
            'eligible_user_ids' => 'nullable|array',
            'eligible_user_ids.*' => 'exists:users,id',
        ]);

        $projectPosition->update($request->only('name'));

        if ($request->has('eligible_user_ids')) {
            $projectPosition->eligibleUsers()->sync($request->input('eligible_user_ids', []));
        }

        $projectPosition->load('eligibleUsers');
        $projectPosition->eligible_user_ids = $projectPosition->eligibleUsers->pluck('id');

        return response()->json($projectPosition);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/project-positions/{id}",
     *     summary="Delete a project position",
     *     tags={"Project Positions"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project position deleted successfully"
     *     )
     * )
     */
    public function destroy(ProjectPosition $projectPosition): \Illuminate\Http\JsonResponse
    {
        $projectPosition->delete();

        return response()->json(['message' => 'Project position deleted successfully']);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/project-positions/dropdown",
     *     summary="Get project positions for dropdown",
     *     tags={"Project Positions"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of project positions formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $positions = ProjectPosition::all()->map(function ($position) {
            return [
                'value' => $position->id,
                'label' => $position->name,
            ];
        });

        return response()->json($positions);
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
