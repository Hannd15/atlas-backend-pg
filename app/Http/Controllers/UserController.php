<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API endpoints for managing users and their project position eligibilities"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pg/users",
     *     summary="Get all users",
     *     tags={"Users"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users with comma-separated project position eligibility names",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="project_position_eligibility_names", type="string", description="Comma-separated position names"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $users = User::with('eligiblePositions')->orderBy('updated_at', 'desc')->get();

        $users->each(function ($user) {
            $user->project_position_eligibility_names = $user->eligiblePositions->pluck('name')->implode(', ');
        });

        return response()->json($users);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/{id}",
     *     summary="Get a specific user",
     *     tags={"Users"},
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
     *         description="User details with array of project position eligibility IDs",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="project_position_eligibility_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show(User $user): \Illuminate\Http\JsonResponse
    {
        $user->load('eligiblePositions');

        $user->project_position_eligibility_ids = $user->eligiblePositions->pluck('id');

        return response()->json($user);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/users/{id}",
     *     summary="Update a user",
     *     tags={"Users"},
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
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(
     *                 property="project_position_eligibility_ids",
     *                 type="array",
     *                 description="Array of project position IDs. Empty array removes all eligibilities. Null or missing field is ignored.",
     *
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$user->id,
            'project_position_eligibility_ids' => 'nullable|array',
            'project_position_eligibility_ids.*' => 'exists:project_positions,id',
        ]);

        // Update user basic fields if provided
        $user->update($request->only('name', 'email'));

        // Handle project position eligibilities sync if field is provided
        if ($request->has('project_position_eligibility_ids')) {
            $user->eligiblePositions()->sync($request->input('project_position_eligibility_ids'));
        }

        // Reload and enrich with eligibility data
        $user->load('eligiblePositions');
        $user->project_position_eligibility_ids = $user->eligiblePositions->pluck('id');

        return response()->json($user);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/dropdown",
     *     summary="Get users for dropdown",
     *     tags={"Users"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(): \Illuminate\Http\JsonResponse
    {
        $users = User::all()->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
            ];
        });

        return response()->json($users);
    }
}
