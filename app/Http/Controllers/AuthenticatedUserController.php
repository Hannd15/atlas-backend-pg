<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedUserController extends AtlasAuthenticatedController
{
    /**
     * @OA\Get(
     *     path="/api/pg/auth/user-profile",
     *     summary="Retrieve the authenticated user's profile",
     *     description="Returns the raw Atlas user payload along with the user's project ids and project group id maintained locally.",
     *     tags={"Authentication"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated user details",
     *
     *             @OA\JsonContent(
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=123),
     *                 @OA\Property(property="name", type="string", example="Alice Example"),
     *                 @OA\Property(property="email", type="string", example="alice@example.com"),
     *                 @OA\Property(property="avatar", type="string", example="https://example.com/avatar.png"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="roles_list", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="roles_names", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="permissions_list", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="permissions_names", type="array", @OA\Items(type="string")),
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *
     *                     @OA\Items(type="integer", example=42)
     *                 ),
     *
     *                 @OA\Property(property="group_id", type="integer", nullable=true, example=7)
     *             )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid token"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $payload = $this->atlasAuthService->verifyToken((string) $request->bearerToken());
        $user = $payload['user'] ?? [];

        if (! is_array($user)) {
            $user = (array) $user;
        }
        $userId = $this->extractUserId($user);

        return response()->json(array_merge($user, [
            'projects' => $this->projectIdsForUser($userId),
            'group_id' => $this->groupIdForUser($userId),
        ]));
    }

    protected function extractUserId(array $atlasUser): ?int
    {
        $userId = $atlasUser['id'] ?? null;

        if ($userId === null || $userId === '') {
            return null;
        }

        return (int) $userId;
    }

    protected function groupIdForUser(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $groupId = Project::query()
            ->whereHas('groups.members', fn ($query) => $query->where('user_id', $userId))
            ->join('project_groups', 'project_groups.project_id', '=', 'projects.id')
            ->join('group_members', 'group_members.group_id', '=', 'project_groups.id')
            ->where('group_members.user_id', $userId)
            ->value('project_groups.id');

        if ($groupId !== null) {
            return (int) $groupId;
        }

        return ProjectGroup::query()
            ->join('group_members', 'group_members.group_id', '=', 'project_groups.id')
            ->where('group_members.user_id', $userId)
            ->orderBy('project_groups.id')
            ->value('project_groups.id');
    }

    protected function projectIdsForUser(?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        return Project::query()
            ->whereHas('groups.members', fn ($query) => $query->where('user_id', $userId))
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }
}
