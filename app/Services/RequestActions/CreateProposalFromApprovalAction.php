<?php

namespace App\Services\RequestActions;

use App\Models\AcademicPeriod;
use App\Models\AcademicPeriodState;
use App\Models\ApprovalRequest;
use App\Models\Phase;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Proposal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateProposalFromApprovalAction implements ApprovalRequestAction
{
    public function handleApproval(ApprovalRequest $request): void
    {
        $payload = $this->payload($request);

        if ($payload === null) {
            return;
        }

        DB::transaction(function () use ($payload) {
            $proposal = Proposal::create($payload['proposal']);

            if ($this->shouldCreateProject($payload)) {
                $this->createProjectFromProposal($proposal);
            }
        });
    }

    public function handleRejection(ApprovalRequest $request): void {}

    protected function payload(ApprovalRequest $request): ?array
    {
        $proposalData = $this->proposalData($request);

        if ($proposalData === null) {
            return null;
        }

        $origin = $request->action_payload['origin'] ?? 'teacher';

        return [
            'proposal' => $proposalData,
            'origin' => is_string($origin) ? $origin : 'teacher',
        ];
    }

    protected function proposalData(ApprovalRequest $request): ?array
    {
        $payload = $request->action_payload['proposal'] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        $required = [
            'title',
            'proposal_type_id',
            'proposal_status_id',
            'proposer_id',
            'thematic_line_id',
        ];

        foreach ($required as $field) {
            if (! Arr::exists($payload, $field)) {
                return null;
            }
        }

        return [
            'title' => (string) $payload['title'],
            'description' => $payload['description'] ?? null,
            'proposal_type_id' => (int) $payload['proposal_type_id'],
            'proposal_status_id' => (int) $payload['proposal_status_id'],
            'proposer_id' => (int) $payload['proposer_id'],
            'preferred_director_id' => isset($payload['preferred_director_id'])
                ? (int) $payload['preferred_director_id']
                : null,
            'thematic_line_id' => (int) $payload['thematic_line_id'],
        ];
    }

    protected function shouldCreateProject(array $payload): bool
    {
        return ($payload['origin'] ?? 'teacher') === 'student';
    }

    protected function createProjectFromProposal(Proposal $proposal): void
    {
        $statusId = ProjectStatus::query()
            ->where('name', 'En proceso')
            ->value('id');

        if (! $statusId) {
            $statusId = ProjectStatus::query()->firstOrCreate(['name' => 'En proceso'])->id;
        }

        Project::create([
            'proposal_id' => $proposal->id,
            'title' => $proposal->title,
            'description' => $proposal->description,
            'thematic_line_id' => $proposal->thematic_line_id,
            'status_id' => $statusId,
            'phase_id' => $this->firstPhaseOfCurrentPeriodId(),
        ]);
    }

    protected function firstPhaseOfCurrentPeriodId(): int
    {
        $activeStateId = AcademicPeriodState::activeId();

        $period = AcademicPeriod::query()
            ->where('state_id', $activeStateId)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if (! $period) {
            $period = AcademicPeriod::query()
                ->where('state_id', $activeStateId)
                ->orderBy('start_date')
                ->orderBy('id')
                ->first();
        }

        if (! $period) {
            throw new RuntimeException('No active academic period available to assign a phase.');
        }

        $phase = Phase::query()
            ->where('period_id', $period->id)
            ->orderBy('id')
            ->first();

        if (! $phase) {
            $phase = Phase::create([
                'period_id' => $period->id,
                'name' => 'Fase inicial automática',
                'start_date' => $period->start_date,
                'end_date' => $period->end_date,
            ]);
        }

        return $phase->id;
    }
}
