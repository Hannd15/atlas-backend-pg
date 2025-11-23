<?php

namespace App\Console\Commands;

use App\Models\ApprovalRequest;
use App\Services\ApprovalRequestService;
use Illuminate\Console\Command;

class CreateApprovalRequest extends Command
{
    protected $signature = 'pg:create-approval-request
        {title? : Title for the approval request}
        {requested_by? : User ID that submits the request}
        {--action-key=noop : Action key stored with the request}
        {--description= : Optional description}
        {--recipient=* : Recipient user IDs (repeatable)}';

    protected $description = 'Quick helper to create ApprovalRequest records without payload for testing.';

    public function __construct(protected ApprovalRequestService $approvalRequestService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $title = $this->argument('title') ?? $this->askRequired('Title');
        $requestedBy = (int) ($this->argument('requested_by') ?? $this->askRequired('Requested by (user id)'));
        $actionKey = $this->option('action-key') ?: 'noop';
        $description = $this->option('description') ?: null;

        $recipientIds = collect($this->option('recipient'))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        if (empty($recipientIds)) {
            $recipientIds = [$requestedBy];
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'requested_by' => $requestedBy,
            'action_key' => $actionKey,
            'action_payload' => null,
            'status' => ApprovalRequest::STATUS_PENDING,
        ];

        $request = $this->approvalRequestService->create($payload, $recipientIds);

        $this->info("Created approval request #{$request->id}");
        $this->line("  Title: {$request->title}");
        $this->line('  Recipients: '.implode(', ', $request->recipients->pluck('user_id')->all()));

        return self::SUCCESS;
    }

    protected function askRequired(string $question): string
    {
        do {
            $value = $this->ask($question);
        } while (! $value);

        return $value;
    }
}
