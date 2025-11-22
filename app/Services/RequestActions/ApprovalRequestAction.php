<?php

namespace App\Services\RequestActions;

use App\Models\ApprovalRequest;

interface ApprovalRequestAction
{
    public function handleApproval(ApprovalRequest $request): void;

    public function handleRejection(ApprovalRequest $request): void;
}
