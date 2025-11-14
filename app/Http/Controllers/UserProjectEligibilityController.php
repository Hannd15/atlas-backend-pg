<?php

namespace App\Http\Controllers;

use App\Models\ProjectPosition;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * @OA\Tag(
 *     name="User Project Eligibilities",
 *     description="API endpoints for managing user project position eligibilities"
 * )
 */
class UserProjectEligibilityController extends Controller
{
    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function buildUserSummaries(): Collection
    {
        return User::with(['eligiblePositions' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get()->map(function (User $user) {
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'project_position_names' => $user->eligiblePositions->pluck('name')->implode(', '),
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function buildPositionSummaries(): Collection
    {
        return ProjectPosition::with(['eligibleUsers' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get()->map(function (ProjectPosition $position) {
            return [
                'project_position_id' => $position->id,
                'project_position_name' => $position->name,
                'user_names' => $position->eligibleUsers->pluck('name')->implode(', '),
            ];
        });
    }

    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities/by-user",
     *     summary="Get user eligibility summaries",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users with comma-separated eligible project positions",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="user_id", type="integer", example=5),
     *                 @OA\Property(property="user_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="project_position_names", type="string", example="Director, Jurado")
     *             )
     *         )
     *     )
     * )
     */
    public function byUser(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildUserSummaries()->values());
    }

    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities/by-position",
     *     summary="Get project position eligibility summaries",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of project positions with comma-separated eligible users",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="project_position_id", type="integer", example=3),
     *                 @OA\Property(property="project_position_name", type="string", example="Director"),
     *                 @OA\Property(property="user_names", type="string", example="Jane Doe, John Smith")
     *             )
     *         )
     *     )
     * )
     */
    public function byPosition(): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildPositionSummaries()->values());
    }
}
