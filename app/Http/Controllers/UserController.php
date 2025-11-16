<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API endpoints for managing users and their project position eligibilities"
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
     *                 @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="project_position_eligibility_names", type="string", description="Comma-separated position names"),
     *                 @OA\Property(property="proposal_names", type="string", description="Comma-separated proposal names"),
     *                 @OA\Property(
     *                     property="eligible_positions",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="pivot", type="object", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="proposals", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="preferred_proposals", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $users = User::with(['eligiblePositions', 'proposals', 'preferredProposals'])
            ->orderBy('updated_at', 'desc')
            ->get();

        if ($users->isEmpty()) {
            return response()->json([]);
        }

        $token = $this->requireToken($request->bearerToken());

        $remoteUsers = $this->remoteUsersById($token, $users->pluck('id')->all());

        $response = $users->map(fn (User $user) => $this->formatUserWithRemoteData(
            $user,
            $remoteUsers[$user->id] ?? []
        ));

        return response()->json($response->values());
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
     *             @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="project_position_eligibility_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="proposal_names", type="string", description="Comma-separated proposal names"),
     *             @OA\Property(property="eligible_positions", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="proposals", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="preferred_proposals", type="array", @OA\Items(type="object")),
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
    public function show(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        $user->load('eligiblePositions', 'proposals', 'preferredProposals');

        $token = $this->requireToken($request->bearerToken());

        $remoteUser = $this->atlasUserService->getUser($token, $user->id);

        return response()->json($this->formatUserWithRemoteData($user, $remoteUser, includeEligibilityIds: true));
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
            'email' => 'sometimes|required|email|max:255',
            'project_position_eligibility_ids' => 'nullable|array',
            'project_position_eligibility_ids.*' => 'exists:project_positions,id',
        ]);

        $token = $this->requireToken($request->bearerToken());
        $payload = array_filter(
            $request->only(['name', 'email']),
            fn ($value) => $value !== null
        );

        $remoteUser = [];

        if (! empty($payload)) {
            $remoteUser = $this->atlasUserService->updateUser($token, $user->id, $payload);

            $user->fill($payload);

            if ($user->isDirty()) {
                $user->save();
            }
        } else {
            $remoteUser = $this->atlasUserService->getUser($token, $user->id);
        }

        if ($request->has('project_position_eligibility_ids')) {
            $user->eligiblePositions()->sync($request->input('project_position_eligibility_ids', []));
        }

        $user->load('eligiblePositions', 'proposals', 'preferredProposals');

        return response()->json($this->formatUserWithRemoteData($user, $remoteUser, includeEligibilityIds: true));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/users/dropdown",
     *     summary="Get users for dropdown",
     *     tags={"Users"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users formatted for dropdowns",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(property="value", type="integer", example=5),
     *                 @OA\Property(property="label", type="string", example="Jane Doe")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $localUsers = User::with(['eligiblePositions', 'proposals', 'preferredProposals'])
            ->orderBy('name')
            ->get();

        if ($localUsers->isEmpty()) {
            return response()->json([]);
        }

        $localIds = $localUsers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $token = $this->requireToken($request->bearerToken());

        $allowed = array_flip($localIds);

        $remoteOptions = collect($this->atlasUserService->dropdown($token))
            ->filter(function ($option) use ($allowed) {
                $value = $option['value'] ?? null;

                if ($value === null) {
                    return false;
                }

                $id = (int) $value;

                return array_key_exists($id, $allowed);
            })
            ->mapWithKeys(fn ($option) => [
                (int) ($option['value'] ?? 0) => (string) ($option['label'] ?? ''),
            ]);

        $options = $localUsers
            ->map(function (User $user) use ($remoteOptions) {
                $label = $remoteOptions[$user->id] ?? '';

                if ($label === '') {
                    $label = "User #{$user->id}";
                }

                return [
                    'value' => $user->id,
                    'label' => $label,
                    'project_position_eligibility_names' => $user->eligiblePositions->pluck('name')->implode(', '),
                    'proposal_names' => $user->proposals->pluck('title')
                        ->merge($user->preferredProposals->pluck('title'))
                        ->filter()
                        ->unique()
                        ->implode(', '),
                ];
            })
            ->sortBy('label')
            ->values();

        return response()->json($options);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, mixed>>
     */
    protected function remoteUsersById(string $token, array $userIds): array
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $lookup = array_flip($ids->all());

        $remoteUsers = [];

        foreach ($this->atlasUserService->listUsers($token) as $remoteUser) {
            $id = isset($remoteUser['id']) ? (int) $remoteUser['id'] : null;

            if ($id === null) {
                continue;
            }

            if (! array_key_exists($id, $lookup)) {
                continue;
            }

            $remoteUsers[$id] = $remoteUser;
        }

        return $remoteUsers;
    }

    protected function formatUserWithRemoteData(User $user, array $remoteData, bool $includeEligibilityIds = false): array
    {
        $user->loadMissing('eligiblePositions', 'proposals', 'preferredProposals');

        $local = $user->toArray();
        $local['project_position_eligibility_names'] = $user->eligiblePositions->pluck('name')->implode(', ');
        $local['proposal_names'] = $user->proposals->pluck('title')
            ->merge($user->preferredProposals->pluck('title'))
            ->filter()
            ->unique()
            ->implode(', ');
        $local['created_at'] = optional($user->created_at)->toJSON();
        $local['updated_at'] = optional($user->updated_at)->toJSON();

        if ($includeEligibilityIds) {
            $local['project_position_eligibility_ids'] = $user->eligiblePositions->pluck('id')->values()->all();
        } else {
            unset($local['project_position_eligibility_ids']);
        }

        $response = $local;

        foreach ($remoteData as $key => $value) {
            $response[$key] = $value;
        }

        foreach (['eligible_positions', 'proposals', 'preferred_proposals'] as $relationshipKey) {
            $response[$relationshipKey] = $local[$relationshipKey] ?? [];
        }

        $response['proposal_names'] = $local['proposal_names'];

        if ($includeEligibilityIds) {
            $response['project_position_eligibility_ids'] = $local['project_position_eligibility_ids'] ?? [];
            unset($response['project_position_eligibility_names']);
        } else {
            $response['project_position_eligibility_names'] = $local['project_position_eligibility_names'];
        }

        return $response;
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
