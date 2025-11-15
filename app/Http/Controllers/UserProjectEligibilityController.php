<?php

namespace App\Http\Controllers;

use App\Models\ProjectPosition;
use App\Models\User;
use App\Services\AtlasUserService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @OA\Tag(
 *     name="User Project Eligibilities",
 *     description="API endpoints for managing user project position eligibilities"
 * )
 */
class UserProjectEligibilityController extends Controller
{
    public function __construct(
        protected AtlasUserService $atlasUserService
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function buildUserSummaries(?string $token): Collection
    {
        $token = $this->requireToken($token);
        $users = User::with(['eligiblePositions' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get();

        if ($users->isEmpty()) {
            return collect();
        }

        $names = $this->atlasUserService->namesByIds($token, $users->pluck('id')->all());

        return $users->map(function (User $user) use ($names) {
            return [
                'user_id' => $user->id,
                'user_name' => $names[$user->id] ?? $user->name,
                'project_position_names' => $user->eligiblePositions->pluck('name')->implode(', '),
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function buildPositionSummaries(?string $token): Collection
    {
        $token = $this->requireToken($token);
        $positions = ProjectPosition::with(['eligibleUsers' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get();

        if ($positions->isEmpty()) {
            return collect();
        }

        $userIds = $positions
            ->flatMap(fn (ProjectPosition $position) => $position->eligibleUsers->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $remoteNames = empty($userIds)
            ? []
            : $this->atlasUserService->namesByIds($token, $userIds);

        return $positions->map(function (ProjectPosition $position) use ($remoteNames) {
            $eligibleIds = $position->eligibleUsers->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();

            return [
                'project_position_id' => $position->id,
                'project_position_name' => $position->name,
                'user_names' => $eligibleIds
                    ->map(fn ($id) => $remoteNames[$id] ?? "User #{$id}")
                    ->filter()
                    ->unique()
                    ->implode(', '),
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
    public function byUser(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildUserSummaries((string) $request->bearerToken())->values());
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
    public function byPosition(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->buildPositionSummaries((string) $request->bearerToken())->values());
    }

    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities/by-user/dropdown",
     *     summary="Dropdown of users with eligibility labels",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Value-label pairs",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=5),
     *                 @OA\Property(property="label", type="string", example="Jane Doe - Director, Jurado")
     *             )
     *         )
     *     )
     * )
     */
    public function byUserDropdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $items = $this->buildUserSummaries((string) $request->bearerToken())
            ->map(function (array $item) {
                $label = $item['project_position_names'] !== ''
                    ? $item['user_name'].' - '.$item['project_position_names']
                    : $item['user_name'];

                return [
                    'value' => $item['user_id'],
                    'label' => $label,
                ];
            })
            ->values();

        return response()->json($items);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/user-project-eligibilities/by-position/dropdown",
     *     summary="Dropdown of positions with eligible user labels",
     *     tags={"User Project Eligibilities"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Value-label pairs",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=3),
     *                 @OA\Property(property="label", type="string", example="Director - Jane Doe")
     *             )
     *         )
     *     )
     * )
     */
    public function byPositionDropdown(Request $request): \Illuminate\Http\JsonResponse
    {
        $items = $this->buildPositionSummaries((string) $request->bearerToken())
            ->map(function (array $item) {
                $label = $item['user_names'] !== ''
                    ? $item['project_position_name'].' - '.$item['user_names']
                    : $item['project_position_name'];

                return [
                    'value' => $item['project_position_id'],
                    'label' => $label,
                ];
            })
            ->values();

        return response()->json($items);
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
