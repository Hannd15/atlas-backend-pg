<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalActionKey;
use App\Http\Requests\Proposal\StoreProposalRequest;
use App\Http\Requests\Proposal\UpdateProposalRequest;
use App\Models\ApprovalRequest;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\ProposalType;
use App\Services\ApprovalRequestService;
use App\Services\AtlasAuthService;
use App\Services\AtlasUserService;
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
 *     @OA\Property(property="thematic_line_id", type="integer", nullable=true, example=4),
 *     @OA\Property(property="preferred_director_id", type="integer", nullable=true, example=32),
 *     @OA\Property(property="proposal_type_name", type="string", nullable=true, example="Docente"),
 *     @OA\Property(property="proposal_status_name", type="string", nullable=true, example="Pendiente"),
 *     @OA\Property(property="proposer_name", type="string", nullable=true, example="Laura Mejía"),
 *     @OA\Property(property="preferred_director_name", type="string", nullable=true, example="Ing. Carlos"),
 *     @OA\Property(property="thematic_line_name", type="string", nullable=true, example="IoT"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProposalController extends AtlasAuthenticatedController
{
    public function __construct(
        AtlasAuthService $atlasAuthService,
        protected AtlasUserService $atlasUserService,
        protected ApprovalRequestService $approvalRequestService
    ) {
        parent::__construct($atlasAuthService);
    }

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

        $type = $this->determineProposalType($request);
        $proposalTypeId = $type['id'];
        $proposalTypeCode = $type['code'];
        $statusId = $validated['proposal_status_id'] ?? $this->defaultStatusId();
        $proposerId = $this->resolveAuthenticatedUserId($request);

        $proposalData = $this->buildProposalData($validated, $proposalTypeId, $statusId, $proposerId);

        if ($this->requiresTeacherApproval($proposalTypeCode)) {
            return $this->initiateCommitteeApproval($request, $proposalData, $proposerId);
        }

        if ($this->requiresStudentApproval($proposalTypeCode)) {
            return $this->initiateStudentApprovalFlow($request, $proposalData, $proposerId);
        }

        $proposal = Proposal::create($proposalData);

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
            'thematic_line_id' => $proposal->thematic_line_id,
            'preferred_director_id' => $proposal->preferred_director_id,
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
            'thematic_line_id' => $proposal->thematic_line_id,
            'preferred_director_id' => $proposal->preferred_director_id,
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

    protected function determineProposalType(Request $request): array
    {
        $userData = $this->resolveAtlasUser($request);

        $roles = $this->extractRoleNames($userData);

        $typeCode = $this->inferTypeCodeFromRoles($roles);
        $typeId = $this->typeId($typeCode);

        if ($typeId === null) {
            $typeId = $this->typeId('made_by_student');
            $typeCode = 'made_by_student';
        }

        if ($typeId === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Proposal type configuration is missing.',
            ], 503));
        }

        return [
            'id' => $typeId,
            'code' => $typeCode,
        ];
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

    protected function buildProposalData(array $validated, int $proposalTypeId, ?int $statusId, int $proposerId): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'proposal_type_id' => $proposalTypeId,
            'proposal_status_id' => $statusId,
            'proposer_id' => $proposerId,
            'preferred_director_id' => $validated['preferred_director_id'] ?? null,
            'thematic_line_id' => $validated['thematic_line_id'],
        ];
    }

    protected function requiresTeacherApproval(string $typeCode): bool
    {
        return $typeCode === 'made_by_teacher';
    }

    protected function requiresStudentApproval(string $typeCode): bool
    {
        return $typeCode === 'made_by_student';
    }

    protected function initiateCommitteeApproval(Request $request, array $proposalData, int $proposerId, string $origin = 'teacher'): JsonResponse
    {
        $token = trim((string) $request->bearerToken());
        $recipientIds = $this->resolveCommitteeRecipientIds($token);

        if (empty($recipientIds)) {
            throw new HttpResponseException(response()->json([
                'message' => 'No hay integrantes del comité configurados para aprobar propuestas.',
            ], 422));
        }

        $approvalRequest = $this->approvalRequestService->create([
            'title' => "Aprobación de propuesta: {$proposalData['title']}",
            'description' => 'El comité debe revisar y aprobar esta propuesta antes de su creación.',
            'requested_by' => $proposerId,
            'action_key' => ApprovalActionKey::ProposalCommittee->value,
            'action_payload' => $this->buildProposalApprovalPayload($proposalData, $proposerId, $recipientIds, $origin),
            'status' => ApprovalRequest::STATUS_PENDING,
        ], $recipientIds);

        return $this->approvalPendingResponse($approvalRequest, 'La propuesta fue enviada al comité para aprobación.');
    }

    protected function initiateStudentApprovalFlow(Request $request, array $proposalData, int $proposerId): JsonResponse
    {
        $preferredDirectorId = $proposalData['preferred_director_id'];

        if ($preferredDirectorId === null) {
            throw new HttpResponseException(response()->json([
                'message' => 'Las propuestas de estudiantes requieren un director preferido.',
            ], 422));
        }

        $token = trim((string) $request->bearerToken());
        $committeeRecipientIds = $this->resolveCommitteeRecipientIds($token);

        if (empty($committeeRecipientIds)) {
            throw new HttpResponseException(response()->json([
                'message' => 'No hay integrantes del comité configurados para aprobar propuestas.',
            ], 422));
        }

        $payload = $this->buildProposalApprovalPayload($proposalData, $proposerId, $committeeRecipientIds, 'student');

        $approvalRequest = $this->approvalRequestService->create([
            'title' => "Dirección de propuesta estudiantil: {$proposalData['title']}",
            'description' => 'El director preferido debe aprobar la propuesta antes de escalarla al comité.',
            'requested_by' => $proposerId,
            'action_key' => ApprovalActionKey::ProposalStudentDirector->value,
            'action_payload' => $payload,
            'status' => ApprovalRequest::STATUS_PENDING,
        ], [(int) $preferredDirectorId]);

        return $this->approvalPendingResponse($approvalRequest, 'La propuesta fue enviada al director preferido para aprobación.');
    }

    protected function resolveCommitteeRecipientIds(string $token): array
    {
        $users = $this->atlasUserService->usersByPermission($token, 'parte del comité de proyectos de grado');

        return collect($users)
            ->map(fn ($user) => $user['id'] ?? null)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function buildProposalApprovalPayload(array $proposalData, int $proposerId, array $committeeRecipientIds, string $origin): array
    {
        return [
            'proposal' => $proposalData,
            'requested_by' => $proposerId,
            'committee_recipient_ids' => $committeeRecipientIds,
            'origin' => $origin,
        ];
    }

    protected function approvalPendingResponse(ApprovalRequest $approvalRequest, string $message): JsonResponse
    {
        $approvalRequest->loadMissing('recipients');

        return response()->json([
            'message' => $message,
            'approval_request_id' => $approvalRequest->id,
            'status' => $approvalRequest->status,
            'title' => $approvalRequest->title,
            'recipient_ids' => $approvalRequest->recipients->pluck('user_id')->values()->all(),
        ], 202);
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
