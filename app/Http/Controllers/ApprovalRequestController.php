<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovalRequest\DecideApprovalRequestRequest;
use App\Http\Requests\ApprovalRequest\StoreApprovalRequestRequest;
use App\Models\ApprovalRequest;
use App\Services\ApprovalRequestService;
use App\Services\AtlasAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Approval Requests",
 *     description="Endpoints for multi-approver workflows"
 * )
 *
 * @OA\Schema(
 *     schema="ApprovalRequestRecipient",
 *     type="object",
 *
 *     @OA\Property(property="user_id", type="integer", example=12),
 *     @OA\Property(property="decision", type="string", nullable=true, example="approved"),
 *     @OA\Property(property="decision_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ApprovalRequestPayload",
 *     type="object",
 *     required={"title","action_key","recipient_ids"},
 *
 *     @OA\Property(property="title", type="string", example="Actualizar la rúbrica del entregable"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="action_key", type="string", example="noop"),
 *     @OA\Property(property="action_payload", type="object", nullable=true),
 *     @OA\Property(
 *         property="recipient_ids",
 *         type="array",
 *
 *         @OA\Items(type="integer", example=7)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ApprovalRequestResource",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", example=55),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="resolved_decision", type="string", nullable=true, example="approved"),
 *     @OA\Property(property="resolved_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="action_key", type="string", example="noop"),
 *     @OA\Property(property="action_payload", type="object", nullable=true),
 *     @OA\Property(property="requested_by", type="integer", example=8),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="recipients", type="array", @OA\Items(ref="#/components/schemas/ApprovalRequestRecipient")),
 *     @OA\Property(property="pending_decision", type="boolean", nullable=true)
 * )
 */
class ApprovalRequestController extends AtlasAuthenticatedController
{
    public function __construct(
        AtlasAuthService $atlasAuthService,
        protected ApprovalRequestService $approvalRequestService
    ) {
        parent::__construct($atlasAuthService);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/approval-requests",
     *     summary="List approval requests",
     *     tags={"Approval Requests"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Approval requests",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ApprovalRequestResource"))
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $requests = ApprovalRequest::with('recipients')->orderByDesc('created_at')->get();

        return response()->json($requests->map(fn (ApprovalRequest $request) => $this->transform($request)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/approval-requests",
     *     summary="Create a new approval request",
     *     tags={"Approval Requests"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ApprovalRequestPayload")),
     *
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/ApprovalRequestResource"))
     * )
     */
    public function store(StoreApprovalRequestRequest $request): JsonResponse
    {
        $payload = $request->sanitizedPayload();
        $payload['requested_by'] = $this->resolveAuthenticatedUserId($request);
        $payload['status'] = ApprovalRequest::STATUS_PENDING;

        $approvalRequest = $this->approvalRequestService->create($payload, $request->recipientIds());

        return response()->json($this->transform($approvalRequest), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/pg/approval-requests/{approval_request}",
     *     summary="Show approval request",
     *     tags={"Approval Requests"},
     *
     *     @OA\Parameter(name="approval_request", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Approval request", @OA\JsonContent(ref="#/components/schemas/ApprovalRequestResource")),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $approvalRequest->loadMissing('recipients');

        return response()->json($this->transform($approvalRequest, $this->optionalViewerId($request)));
    }

    /**
     * @OA\Get(
     *     path="/api/pg/approval-requests/relevant",
     *     summary="List approval requests relevant to the authenticated user",
     *     tags={"Approval Requests"},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Relevant approval requests",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ApprovalRequestResource"))
     *     )
     * )
     */
    public function relevant(Request $request): JsonResponse
    {
        $userId = $this->resolveAuthenticatedUserId($request);

        $requests = ApprovalRequest::with('recipients')
            ->where(function ($query) use ($userId) {
                $query->where('requested_by', $userId)
                    ->orWhereHas('recipients', fn ($relation) => $relation->where('user_id', $userId));
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests->map(fn (ApprovalRequest $approvalRequest) => $this->transform($approvalRequest, $userId)));
    }

    /**
     * @OA\Post(
     *     path="/api/pg/approval-requests/{approval_request}/decision",
     *     summary="Approve or reject a request",
     *     tags={"Approval Requests"},
     *
     *     @OA\Parameter(name="approval_request", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"decision"}, @OA\Property(property="decision", type="string", enum={"approved","rejected"}))),
     *
     *     @OA\Response(response=200, description="Updated request", @OA\JsonContent(ref="#/components/schemas/ApprovalRequestResource")),
     *     @OA\Response(response=403, description="Not a recipient"),
     *     @OA\Response(response=409, description="Already decided"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function decide(DecideApprovalRequestRequest $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $userId = $this->resolveAuthenticatedUserId($request);
        $result = $this->approvalRequestService->recordDecision($approvalRequest, $userId, $request->decision());

        return response()->json($this->transform($result->load('recipients'), $userId));
    }

    protected function transform(ApprovalRequest $approvalRequest, ?int $viewerId = null): array
    {
        $approvalRequest->loadMissing('recipients');

        $recipients = $approvalRequest->recipients->map(fn ($recipient) => [
            'user_id' => $recipient->user_id,
            'decision' => $recipient->decision,
            'decision_at' => optional($recipient->decision_at)->toDateTimeString(),
        ]);

        $viewerRecipient = $viewerId ? $approvalRequest->recipients->firstWhere('user_id', $viewerId) : null;

        return [
            'id' => $approvalRequest->id,
            'title' => $approvalRequest->title,
            'description' => $approvalRequest->description,
            'status' => $approvalRequest->status,
            'resolved_decision' => $approvalRequest->resolved_decision,
            'resolved_at' => optional($approvalRequest->resolved_at)->toDateTimeString(),
            'action_key' => $approvalRequest->action_key,
            'action_payload' => $approvalRequest->action_payload,
            'requested_by' => $approvalRequest->requested_by,
            'recipients' => $recipients,
            'pending_decision' => $viewerRecipient ? $viewerRecipient->decision === null : null,
            'created_at' => optional($approvalRequest->created_at)->toDateTimeString(),
            'updated_at' => optional($approvalRequest->updated_at)->toDateTimeString(),
        ];
    }

    protected function optionalViewerId(Request $request): ?int
    {
        try {
            return $this->resolveAuthenticatedUserId($request);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
