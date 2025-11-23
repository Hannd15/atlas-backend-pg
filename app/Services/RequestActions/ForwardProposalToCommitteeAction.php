<?php

namespace App\Services\RequestActions;

use App\Enums\ApprovalActionKey;
use App\Models\ApprovalRequest;
use App\Services\ApprovalRequestService;
use Illuminate\Support\Arr;

class ForwardProposalToCommitteeAction implements ApprovalRequestAction
{
    public function __construct(protected ApprovalRequestService $approvalRequestService) {}

    public function handleApproval(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        $title = $payload['committee_title'] ?? 'Aprobación de propuesta estudiantil';
        $description = $payload['committee_description'] ?? 'El comité debe revisar y aprobar esta propuesta.';

        $this->approvalRequestService->create([
            'title' => $title,
            'description' => $description,
            'requested_by' => $payload['requested_by'],
            'action_key' => ApprovalActionKey::ProposalCommittee->value,
            'action_payload' => [
                'proposal' => $payload['proposal'],
                'requested_by' => $payload['requested_by'],
                'origin' => 'student',
                'committee_recipient_ids' => $payload['committee_recipient_ids'],
            ],
            'status' => ApprovalRequest::STATUS_PENDING,
        ], $payload['committee_recipient_ids']);
    }

    public function handleRejection(ApprovalRequest $request): void {}

    protected function payload(ApprovalRequest $request): ?array
    {
        $payload = $request->action_payload ?? null;

        if (! is_array($payload)) {
            return null;
        }

        if (! Arr::has($payload, ['proposal', 'requested_by', 'committee_recipient_ids'])) {
            return null;
        }

        $recipientIds = collect($payload['committee_recipient_ids'])
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($recipientIds)) {
            return null;
        }

        $payload['committee_recipient_ids'] = $recipientIds;

        return $payload;
    }
}
