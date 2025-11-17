<?php

namespace App\Http\Controllers;

use App\Models\ProjectGroup;
use App\Services\AtlasUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Group Members",
 *     description="List members of a project group"
 * )
 *
 * @OA\Schema(
 *     schema="GroupMemberResource",
 *     type="object",
 *
 *     @OA\Property(property="user_id", type="integer", example=25),
 *     @OA\Property(property="user_name", type="string", example="Ana López")
 * )
 */
class GroupMemberController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/project-groups/{project_group}/members",
     *     summary="List members of a project group",
     *     tags={"Project Groups"},
     *
     *     @OA\Parameter(name="project_group", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Array of group members",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/GroupMemberResource"))
     *     ),
     *
     *     @OA\Response(response=404, description="Project group not found")
     * )
     */
    public function index(Request $request, ProjectGroup $projectGroup): JsonResponse
    {
        $projectGroup->loadMissing('members');

        $memberIds = $projectGroup->members
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($memberIds)) {
            return response()->json([]);
        }

        $token = trim((string) $request->bearerToken());
        $userNames = $this->atlasUserService->namesByIds($token, $memberIds);

        $members = collect($memberIds)->map(fn ($userId) => [
            'user_id' => $userId,
            'user_name' => $userNames[$userId] ?? "User #{$userId}",
        ]);

        return response()->json($members);
    }
}
