<?php

namespace App\Http\Controllers;

use App\Models\ProjectPosition;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Project Positions",
 *     description="API endpoints for managing project positions"
 * )
 */
class ProjectPositionController extends Controller
{
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
    public function index(): \Illuminate\Http\JsonResponse
    {
        $positions = ProjectPosition::with(['eligibleUsers', 'staff'])->orderBy('updated_at', 'desc')->get();

        $positions->each(function ($position) {
            $position->eligible_user_names = $position->eligibleUsers->pluck('name')->implode(', ');
            $position->staff_names = $position->staff->map(function ($staff) {
                return "Staff #{$staff->id}";
            })->implode(', ');
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
     *             @OA\Property(property="name", type="string")
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
        ]);

        $projectPosition->update($request->only('name'));

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
}
