<?php

namespace App\Services\RequestActions;

use App\Models\ApprovalRequest;
use App\Models\GroupMember;
use App\Models\ProjectGroup;
use Illuminate\Support\Facades\DB;

class AddProjectGroupMemberAction implements ApprovalRequestAction
{
    public function handleApproval(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        DB::transaction(function () use ($payload) {
            GroupMember::query()
                ->where('user_id', $payload['user_id'])
                ->where('group_id', '!=', $payload['group_id'])
                ->delete();

            GroupMember::firstOrCreate([
                'group_id' => $payload['group_id'],
                'user_id' => $payload['user_id'],
            ]);
        });
    }

    public function handleRejection(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        DB::transaction(function () use ($payload) {
            GroupMember::query()
                ->where('group_id', $payload['group_id'])
                ->where('user_id', $payload['user_id'])
                ->delete();
        });
    }

    protected function payload(ApprovalRequest $request): ?array
    {
        $payload = $request->action_payload ?? [];
        $groupId = (int) ($payload['group_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);

        if ($groupId <= 0 || $userId <= 0) {
            return null;
        }

        if (! ProjectGroup::whereKey($groupId)->exists()) {
            return null;
        }

        return [
            'group_id' => $groupId,
            'user_id' => $userId,
        ];
    }
}
