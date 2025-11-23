<?php

namespace App\Services\RequestActions;

use App\Models\ApprovalRequest;
use App\Models\Project;
use App\Models\ProjectPosition;
use App\Models\ProjectStaff;
use App\Models\User;

class AssignProjectStaffAction implements ApprovalRequestAction
{
    public const ACTION_KEY = 'project.staff.assign';

    public function handleApproval(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        ProjectStaff::query()->updateOrCreate(
            [
                'project_id' => $payload['project_id'],
                'project_position_id' => $payload['project_position_id'],
                'user_id' => $payload['user_id'],
            ],
            ['status' => 'active']
        );
    }

    public function handleRejection(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        ProjectStaff::query()
            ->where('project_id', $payload['project_id'])
            ->where('project_position_id', $payload['project_position_id'])
            ->where('user_id', $payload['user_id'])
            ->delete();
    }

    protected function payload(ApprovalRequest $request): ?array
    {
        $data = $request->action_payload ?? [];

        $projectId = (int) ($data['project_id'] ?? 0);
        $projectPositionId = (int) ($data['project_position_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);

        if ($projectId <= 0 || $projectPositionId <= 0 || $userId <= 0) {
            return null;
        }

        if (! Project::whereKey($projectId)->exists()) {
            return null;
        }

        if (! ProjectPosition::whereKey($projectPositionId)->exists()) {
            return null;
        }

        if (! User::whereKey($userId)->exists()) {
            return null;
        }

        return [
            'project_id' => $projectId,
            'project_position_id' => $projectPositionId,
            'user_id' => $userId,
        ];
    }
}
