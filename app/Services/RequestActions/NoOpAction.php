<?php

namespace App\Services\RequestActions;

use App\Models\ApprovalRequest;

class NoOpAction implements ApprovalRequestAction
{
    public function handleApproval(ApprovalRequest $request): void {}

    public function handleRejection(ApprovalRequest $request): void {}
}
