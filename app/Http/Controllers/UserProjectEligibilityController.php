<?php

namespace App\Http\Controllers;

use App\Models\UserProjectEligibility;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="User Project Eligibilities",
 *     description="API endpoints for managing user project position eligibilities"
 * )
 */
class UserProjectEligibilityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities",
     *     summary="Get all user project eligibilities",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of user project eligibilities with user and position names"
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $eligibilities = UserProjectEligibility::with(['user', 'position'])->orderBy('updated_at', 'desc')->get();

        $eligibilities->each(function ($eligibility) {
            $eligibility->user_names = $eligibility->user ? $eligibility->user->name : '';
            $eligibility->project_position_names = $eligibility->position ? $eligibility->position->name : '';
        });

        return response()->json($eligibilities);
    }

    /**
     * @OA\Post(
     *     path="/api/pg/user-project-eligibilities",
     *     summary="Create a new user project eligibility",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"user_id","project_position_id"},
     *
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="project_position_id", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User project eligibility created successfully"
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
            'user_id' => 'required|exists:users,id',
            'project_position_id' => 'required|exists:project_positions,id',
        ]);

        $eligibility = UserProjectEligibility::create($request->only('user_id', 'project_position_id'));

        return response()->json($eligibility, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities/{user_id}/{project_position_id}",
     *     summary="Get a specific user project eligibility",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="project_position_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User project eligibility details with relation IDs"
     *     )
     * )
     */
    public function show(int $userId, int $projectPositionId): \Illuminate\Http\JsonResponse
    {
        $eligibility = UserProjectEligibility::where('user_id', $userId)
            ->where('project_position_id', $projectPositionId)
            ->firstOrFail();

        $eligibility->load('user', 'position');

        $eligibility->user_id = $eligibility->user ? [$eligibility->user->id] : [];
        $eligibility->project_position_id = $eligibility->position ? [$eligibility->position->id] : [];

        return response()->json($eligibility);
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/user-project-eligibilities/{user_id}/{project_position_id}",
     *     summary="Delete a user project eligibility",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="project_position_id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User project eligibility deleted successfully"
     *     )
     * )
     */
    public function destroy(int $userId, int $projectPositionId): \Illuminate\Http\JsonResponse
    {
        $eligibility = UserProjectEligibility::where('user_id', $userId)
            ->where('project_position_id', $projectPositionId)
            ->firstOrFail();

        $eligibility->delete();

        return response()->json(['message' => 'User project eligibility deleted successfully']);
    }
}
