<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Services\RequestActions\ApprovalRequestAction;
use Illuminate\Support\Facades\Log;

class ApprovalRequestActionRunner
{
    public function run(ApprovalRequest $request, string $decision): void
    {
        $handler = $this->resolveHandler($request->action_key);

        if ($handler === null) {
            return;
        }

        if ($decision === ApprovalRequest::DECISION_APPROVED) {
            $handler->handleApproval($request);

            return;
        }

        $handler->handleRejection($request);
    }

    protected function resolveHandler(string $actionKey): ?ApprovalRequestAction
    {
        $handlerMap = config('approval.actions', []);
        $class = $handlerMap[$actionKey] ?? null;

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            Log::warning('Unknown approval request action handler.', [
                'action_key' => $actionKey,
            ]);

            return null;
        }

        $handler = app($class);

        if (! $handler instanceof ApprovalRequestAction) {
            Log::warning('Configured approval request handler does not implement required contract.', [
                'action_key' => $actionKey,
                'handler' => $class,
            ]);

            return null;
        }

        return $handler;
    }
}
