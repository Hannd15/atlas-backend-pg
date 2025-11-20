<?php

namespace App\Http\Controllers;

use App\Services\AtlasUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Proxy endpoints to Atlas authentication module for user management"
 * )
 */
class UserController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/pg/users",
     *     summary="Get all users from Atlas authentication module",
     *     tags={"Users"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users from Atlas authentication service"
     *     )
     * )
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->listUsers($token));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/{id}",
     *     summary="Get a specific user from Atlas authentication module",
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
     *         description="User details from Atlas authentication service"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->getUser($token, $id));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/users",
     *     summary="Create a new user via Atlas authentication module",
     *     tags={"Users"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->createUser($token, $request->all()), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/pg/users/{id}",
     *     summary="Update a user via Atlas authentication module",
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
     *             @OA\Property(property="email", type="string", format="email")
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
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->updateUser($token, $id, $request->all()));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/users/{id}",
     *     summary="Delete a user via Atlas authentication module",
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
     *         description="User deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function destroy(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->deleteUser($token, $id));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/{id}/projects",
     *     summary="Get projects related to a user",
     *     tags={"Users"},
     *     description="Returns projects where user is either a staff member or group member with computed role",
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
     *         description="List of projects related to the user",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="name", type="string", example="Sistema IoT"),
     *                 @OA\Property(property="thematic_line_name", type="string", example="Internet de las Cosas"),
     *                 @OA\Property(property="group_members_names", type="string", example="Ana López, Juan Pérez"),
     *                 @OA\Property(property="user_role", type="string", example="Director", description="Position name from project_staff or 'Miembro del grupo'")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function projects(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $token = trim((string) $request->bearerToken());

        $staffProjects = \App\Models\ProjectStaff::query()
            ->where('user_id', $id)
            ->with([
                'project.thematicLine',
                'project.groups.members',
                'projectPosition',
            ])
            ->get()
            ->map(function ($staff) use ($token) {
                $memberIds = $staff->project->groups
                    ->flatMap(fn ($group) => $group->members->pluck('user_id'))
                    ->filter()
                    ->map(fn ($userId) => (int) $userId)
                    ->unique()
                    ->values()
                    ->all();

                $memberNames = empty($memberIds)
                    ? ''
                    : $this->getMemberNames($memberIds, $token);

                return [
                    'id' => $staff->project->id,
                    'name' => $staff->project->title,
                    'thematic_line_name' => $staff->project->thematicLine?->name,
                    'group_members_names' => $memberNames,
                    'user_role' => $staff->projectPosition?->name ?? 'Staff',
                ];
            });

        $groupProjects = \App\Models\GroupMember::query()
            ->where('user_id', $id)
            ->with([
                'group.project.thematicLine',
                'group.project.groups.members',
            ])
            ->get()
            ->filter(fn ($member) => $member->group?->project !== null)
            ->map(function ($member) use ($token) {
                $project = $member->group->project;

                $memberIds = $project->groups
                    ->flatMap(fn ($group) => $group->members->pluck('user_id'))
                    ->filter()
                    ->map(fn ($userId) => (int) $userId)
                    ->unique()
                    ->values()
                    ->all();

                $memberNames = empty($memberIds)
                    ? ''
                    : $this->getMemberNames($memberIds, $token);

                return [
                    'id' => $project->id,
                    'name' => $project->title,
                    'thematic_line_name' => $project->thematicLine?->name,
                    'group_members_names' => $memberNames,
                    'user_role' => 'Miembro del grupo',
                ];
            });

        $projects = $staffProjects->merge($groupProjects)->unique('id')->values();

        return response()->json($projects);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/dropdown",
     *     summary="Get users dropdown from Atlas authentication module",
     *     tags={"Users"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users from Atlas authentication service formatted for dropdowns"
     *     )
     * )
     */
    public function dropdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $this->requireToken($request->bearerToken());

        return response()->json($this->atlasUserService->dropdown($token));
    }

    protected function getMemberNames(array $userIds, string $token): string
    {
        $userNames = $this->atlasUserService->namesByIds($token, $userIds);

        return collect($userIds)
            ->map(fn ($id) => $userNames[$id] ?? "User #{$id}")
            ->filter()
            ->unique()
            ->implode(', ');
    }

    protected function requireToken(?string $token): string
    {
        $token = trim((string) $token);

        if ($token === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Missing bearer token.',
            ], 401));
        }

        return $token;
    }
}
