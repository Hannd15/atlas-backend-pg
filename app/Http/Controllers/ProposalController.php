<?php

namespace App\Http\Controllers;

use App\Http\Requests\Proposal\StoreProposalRequest;
use App\Http\Requests\Proposal\UpdateProposalRequest;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Services\AtlasAuthService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Proposals",
 *     description="Endpoints for viewing and managing research proposals"
 * )
 *
 * @OA\Schema(
 *     schema="ProposalPayload",
 *     type="object",
 *     required={"title","thematic_line_id"},
 *
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="thematic_line_id", type="integer", example=4),
 *     @OA\Property(property="preferred_director_id", type="integer", nullable=true, example=32),
 *     @OA\Property(property="proposal_status_id", type="integer", nullable=true, example=2)
 * )
 *
 * @OA\Schema(
 *     schema="ProposalResource",
 *     type="object",
 *     description="Minimal proposal representation without embedded relationship objects.",
 *
 *     @OA\Property(property="id", type="integer", example=15),
 *     @OA\Property(property="title", type="string", example="Sistema de monitoreo"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="proposal_type_name", type="string", nullable=true, example="Docente"),
 *     @OA\Property(property="proposal_status_name", type="string", nullable=true, example="Pendiente"),
 *     @OA\Property(property="proposer_name", type="string", nullable=true, example="Laura Mejía"),
 *     @OA\Property(property="preferred_director_name", type="string", nullable=true, example="Ing. Carlos"),
 *     @OA\Property(property="thematic_line_name", type="string", nullable=true, example="IoT"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProposalController extends Controller
{
    public function __construct(protected AtlasAuthService $atlasAuthService) {}

    /**
     * @OA\Get(
     *     path="/api/pg/proposals",
     *     summary="List proposals created by teachers",
     *     tags={"Proposals"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Proposals list",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ProposalResource"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->resolveAtlasUser($request);

        $proposals = Proposal::with($this->defaultRelations())
            ->whereHas('type', fn ($query) => $query->where('code', 'made_by_teacher'))
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($proposals->map(fn (Proposal $proposal) => $this->transformForIndex($proposal)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/proposals",
     *     summary="Create a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProposalPayload")),
     *
     *     @OA\Response(response=201, description="Proposal created", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $proposalTypeId = $this->determineProposalTypeId($request);
        $statusId = $validated['proposal_status_id'] ?? $this->defaultStatusId();
        $proposerId = $this->resolveAuthenticatedUserId($request);

        $proposal = Proposal::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'proposal_type_id' => $proposalTypeId,
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposerId,
            'preferred_director_id' => $validated['preferred_director_id'] ?? null,
            'thematic_line_id' => $validated['thematic_line_id'],
        ]);

        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Show a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal detail", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function show(Proposal $proposal): JsonResponse
    {
        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal));
    }

    /**
     * @OA\Put(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Update a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(ref="#/components/schemas/ProposalPayload")),
     *
     *     @OA\Response(response=200, description="Proposal updated", @OA\JsonContent(ref="#/components/schemas/ProposalResource")),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $validated = $request->validated();

        $proposal->update(array_merge(
            Arr::only($validated, [
                'title',
                'description',
                'proposal_status_id',
                'preferred_director_id',
                'thematic_line_id',
            ]),
            ['proposer_id' => $this->resolveAuthenticatedUserId($request)]
        ));

        $proposal->load($this->defaultRelations());

        return response()->json($this->transformForShow($proposal));
    }

    /**
     * @OA\Delete(
     *     path="/api/pg/proposals/{proposal}",
     *     summary="Delete a proposal",
     *     tags={"Proposals"},
     *
     *     @OA\Parameter(name="proposal", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal deleted"),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function destroy(Proposal $proposal): JsonResponse
    {
        $proposal->delete();

        return response()->json(['message' => 'Proposal deleted successfully']);
    }

    protected function transformForIndex(Proposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'proposal_type_name' => $proposal->type?->name,
            'proposal_status_name' => $proposal->status?->name,
            'proposer_name' => $proposal->proposer?->name,
            'preferred_director_name' => $proposal->preferredDirector?->name,
            'thematic_line_name' => $proposal->thematicLine?->name,
            'created_at' => optional($proposal->created_at)->toDateTimeString(),
            'updated_at' => optional($proposal->updated_at)->toDateTimeString(),
        ];
    }

    protected function transformForShow(Proposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'proposal_type_name' => $proposal->type?->name,
            'proposal_status_name' => $proposal->status?->name,
            'proposer_name' => $proposal->proposer?->name,
            'preferred_director_name' => $proposal->preferredDirector?->name,
            'thematic_line_name' => $proposal->thematicLine?->name,
            'created_at' => optional($proposal->created_at)->toDateTimeString(),
            'updated_at' => optional($proposal->updated_at)->toDateTimeString(),
        ];
    }

    protected function defaultRelations(): array
    {
        return [
            'type',
            'status',
            'thematicLine',
            'proposer',
            'preferredDirector',
        ];
    }

    protected function determineProposalTypeId(Request $request): int
    {
        $userData = $this->resolveAtlasUser($request);

        $roles = $this->extractRoleNames($userData);

        $typeCode = $this->inferTypeCodeFromRoles($roles);
        $typeId = $this->typeId($typeCode);

        if ($typeId === null) {
            $typeId = $this->typeId('made_by_student');
        }

        if ($typeId === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Proposal type configuration is missing.',
            ], 503));
        }

        return $typeId;
    }

    protected function resolveAuthenticatedUserId(Request $request): int
    {
        $user = $this->resolveAtlasUser($request);

        if (! isset($user['id'])) {
            throw new HttpResponseException(response()->json([
                'message' => 'Authenticated user payload is missing an id.',
            ], 503));
        }

        return (int) $user['id'];
    }

    protected function resolveAtlasUser(Request $request): array
    {
        $userData = $request->attributes->get('atlasUser');

        if (is_array($userData) && ! $this->shouldRefreshAtlasUser($userData)) {
            return $userData;
        }

        $token = trim((string) $request->bearerToken());

        if ($token === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Missing bearer token.',
            ], 401));
        }

        $payload = $this->atlasAuthService->verifyToken($token);
        $userData = $payload['user'] ?? null;

        if (! is_array($userData)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Authentication service unavailable.',
            ], 503));
        }

        $request->attributes->set('atlasUser', $userData);

        return $userData;
    }

    protected function shouldRefreshAtlasUser(array $userData): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        $normalized = array_change_key_case($userData, CASE_LOWER);

        if ($normalized === [] || (count($normalized) === 1 && isset($normalized['id']))) {
            return true;
        }

        return ! array_key_exists('roles', $normalized);
    }

    protected function extractRoleNames(array $userData): array
    {
        $roles = [];
        $rawRoles = $userData['roles'] ?? [];

        if (is_string($rawRoles)) {
            $roles[] = $rawRoles;
        } elseif (is_array($rawRoles)) {
            foreach ($rawRoles as $role) {
                if (is_string($role)) {
                    $roles[] = $role;

                    continue;
                }

                if (is_array($role)) {
                    if (isset($role['name'])) {
                        $roles[] = $role['name'];

                        continue;
                    }

                    if (isset($role['label'])) {
                        $roles[] = $role['label'];
                    }
                }
            }
        }

        return $roles;
    }

    protected function inferTypeCodeFromRoles(array $roles): string
    {
        $normalized = collect($roles)->map(fn ($role) => Str::lower($role));

        if ($normalized->contains('director')) {
            return 'made_by_teacher';
        }

        if ($normalized->contains('estudiante')) {
            return 'made_by_student';
        }

        return 'made_by_student';
    }

    protected function typeId(string $code): ?int
    {
        static $cache = [];

        if (! array_key_exists($code, $cache)) {
            $cache[$code] = ProposalType::where('code', $code)->value('id');
        }

        return $cache[$code];
    }

    protected function defaultStatusId(): ?int
    {
        static $defaultStatusId = null;

        if ($defaultStatusId === null) {
            $defaultStatusId = ProposalStatus::where('code', 'pending')->value('id');
        }

        return $defaultStatusId;
    }

    /**
     * @OA\Get(
     *     path="/api/pg/proposals/dropdown",
     *     summary="Get proposals for dropdown",
     *     tags={"Proposals"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pairs ready for selects",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="value", type="integer", example=15),
     *                 @OA\Property(property="label", type="string", example="Sistema de monitoreo")
     *             )
     *         )
     *     )
     * )
     */
    public function dropdown(): JsonResponse
    {
        $proposals = Proposal::orderBy('title')->get()->map(fn (Proposal $proposal) => [
            'value' => $proposal->id,
            'label' => $proposal->title,
        ]);

        return response()->json($proposals);
    }

    // File relation logic moved to ProposalFileController.
}
