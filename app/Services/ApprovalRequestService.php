<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class ApprovalRequestService
{
    public function __construct(protected ApprovalRequestActionRunner $actionRunner) {}

    /**
     * @param  array{title:string,description:?string,requested_by:int,action_key:string,action_payload:?array}  $payload
     * @param  array<int, int>  $recipientIds
     */
    public function create(array $payload, array $recipientIds): ApprovalRequest
    {
        $recipientRows = collect($recipientIds)
            ->unique()
            ->map(fn (int $userId) => ['user_id' => $userId])
            ->values();

        return DB::transaction(function () use ($payload, $recipientRows) {
            $request = ApprovalRequest::create($payload);
            $request->recipients()->createMany($recipientRows);

            return $request->load('recipients.user');
        });
    }

    public function recordDecision(ApprovalRequest $approvalRequest, int $userId, string $decision, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $userId, $decision, $comment) {
            /** @var \App\Models\ApprovalRequest $lockedRequest */
            $lockedRequest = ApprovalRequest::query()
                ->whereKey($approvalRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== ApprovalRequest::STATUS_PENDING) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Request is already resolved.',
                ], 409));
            }

            $recipient = $lockedRequest->recipients()->lockForUpdate()->where('user_id', $userId)->first();

            if (! $recipient) {
                throw new HttpResponseException(response()->json([
                    'message' => 'You are not allowed to act on this request.',
                ], 403));
            }

            if ($recipient->decision !== null) {
                throw new HttpResponseException(response()->json([
                    'message' => 'You already submitted your decision for this request.',
                ], 409));
            }

            $recipient->forceFill([
                'decision' => $decision,
                'decision_at' => now(),
                'comment' => $comment,
            ])->save();

            $lockedRequest->unsetRelation('recipients');
            $lockedRequest->load('recipients.user');

            $this->maybeResolve($lockedRequest);

            return $lockedRequest;
        });
    }

    protected function maybeResolve(ApprovalRequest $request): void
    {
        $totalRecipients = max($request->recipients->count(), 1);
        $threshold = (int) floor($totalRecipients / 2) + 1;

        $approvals = $request->recipients
            ->where('decision', ApprovalRequest::DECISION_APPROVED)
            ->count();
        $rejections = $request->recipients
            ->where('decision', ApprovalRequest::DECISION_REJECTED)
            ->count();

        if ($approvals >= $threshold) {
            $this->resolve($request, ApprovalRequest::DECISION_APPROVED);

            return;
        }

        if ($rejections >= $threshold) {
            $this->resolve($request, ApprovalRequest::DECISION_REJECTED);
        }
    }

    protected function resolve(ApprovalRequest $request, string $decision): void
    {
        if ($request->status !== ApprovalRequest::STATUS_PENDING) {
            return;
        }

        $status = $decision === ApprovalRequest::DECISION_APPROVED
            ? ApprovalRequest::STATUS_APPROVED
            : ApprovalRequest::STATUS_REJECTED;

        $request->forceFill([
            'status' => $status,
            'resolved_decision' => $decision,
            'resolved_at' => now(),
        ])->save();

        DB::afterCommit(function () use ($request, $decision) {
            $fresh = $request->fresh(['recipients']);

            if ($fresh) {
                $this->actionRunner->run($fresh, $decision);
            }
        });
    }
}
