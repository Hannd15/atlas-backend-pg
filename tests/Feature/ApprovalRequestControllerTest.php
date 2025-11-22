<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestRecipient;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ApprovalRequestControllerTest extends TestCase
{
    public function test_user_can_create_approval_request_with_inferred_requester(): void
    {
        $creator = User::factory()->create();
        $recipients = User::factory()->count(2)->create();

        $this->setAtlasUser(['id' => $creator->id, 'roles' => ['Director']]);

        $payload = [
            'title' => 'Actualizar rúbrica',
            'description' => 'Necesitamos aprobación para actualizar la rúbrica del entregable final.',
            'action_key' => 'noop',
            'action_payload' => ['deliverable_id' => 5],
            'recipient_ids' => $recipients->pluck('id')->all(),
        ];

        $response = $this->postJson('/api/pg/approval-requests', $payload, $this->defaultHeaders)
            ->assertCreated()
            ->json();

        $this->assertArrayHasKey('id', $response);
        $this->assertSame($creator->id, $response['requested_by']);
        $this->assertSame('pending', $response['status']);
        $this->assertCount(2, $response['recipients']);

        $this->assertDatabaseHas('approval_requests', [
            'id' => $response['id'],
            'requested_by' => $creator->id,
            'action_key' => 'noop',
        ]);

        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('approval_request_recipients', [
                'approval_request_id' => $response['id'],
                'user_id' => $recipient->id,
                'decision' => null,
            ]);
        }
    }

    public function test_majority_of_recipient_votes_resolves_request(): void
    {
        Date::setTestNow(now());
        $requester = User::factory()->create();
        $recipients = User::factory()->count(3)->create();

        $approvalRequest = ApprovalRequest::create([
            'title' => 'Asignar nuevo director',
            'description' => null,
            'requested_by' => $requester->id,
            'action_key' => 'noop',
            'action_payload' => ['project_id' => 10],
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);

        foreach ($recipients as $recipient) {
            ApprovalRequestRecipient::create([
                'approval_request_id' => $approvalRequest->id,
                'user_id' => $recipient->id,
            ]);
        }

        $this->setAtlasUser(['id' => $recipients[0]->id]);

        $this->postJson("/api/pg/approval-requests/{$approvalRequest->id}/decision", ['decision' => 'approved'], $this->defaultHeaders)
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('approval_request_recipients', [
            'approval_request_id' => $approvalRequest->id,
            'user_id' => $recipients[0]->id,
            'decision' => 'approved',
        ]);

        $this->setAtlasUser(['id' => $recipients[1]->id]);

        $this->postJson("/api/pg/approval-requests/{$approvalRequest->id}/decision", ['decision' => 'approved'], $this->defaultHeaders)
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('resolved_decision', 'approved');

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'approved',
            'resolved_decision' => 'approved',
        ]);
    }

    public function test_non_recipient_cannot_submit_decision(): void
    {
        $requester = User::factory()->create();
        $recipient = User::factory()->create();
        $intruder = User::factory()->create();

        $approvalRequest = ApprovalRequest::factory()->create([
            'requested_by' => $requester->id,
            'action_key' => 'noop',
        ]);

        ApprovalRequestRecipient::factory()->create([
            'approval_request_id' => $approvalRequest->id,
            'user_id' => $recipient->id,
        ]);

        $this->setAtlasUser(['id' => $intruder->id]);

        $this->postJson("/api/pg/approval-requests/{$approvalRequest->id}/decision", ['decision' => 'approved'], $this->defaultHeaders)
            ->assertStatus(403)
            ->assertExactJson(['message' => 'You are not allowed to vote on this request.']);
    }

    public function test_relevant_route_returns_creator_and_recipient_requests(): void
    {
        $requester = User::factory()->create();
        $otherUser = User::factory()->create();
        $recipient = User::factory()->create();

        $createdByUser = ApprovalRequest::factory()->create([
            'requested_by' => $requester->id,
            'action_key' => 'noop',
        ]);
        $assignedToUser = ApprovalRequest::factory()->create([
            'requested_by' => $otherUser->id,
            'action_key' => 'noop',
        ]);
        ApprovalRequestRecipient::factory()->create([
            'approval_request_id' => $assignedToUser->id,
            'user_id' => $requester->id,
        ]);
        $irrelevant = ApprovalRequest::factory()->create([
            'requested_by' => $otherUser->id,
            'action_key' => 'noop',
        ]);
        ApprovalRequestRecipient::factory()->create([
            'approval_request_id' => $irrelevant->id,
            'user_id' => $recipient->id,
        ]);

        $this->setAtlasUser(['id' => $requester->id]);

        $response = $this->getJson('/api/pg/approval-requests/relevant', $this->defaultHeaders)
            ->assertOk()
            ->assertJsonCount(2)
            ->json();

        $this->assertEqualsCanonicalizing(
            [$createdByUser->id, $assignedToUser->id],
            collect($response)->pluck('id')->all()
        );
    }

    protected function setAtlasUser(array $payload): void
    {
        if (! array_key_exists('roles', $payload)) {
            $payload['roles'] = ['Director'];
        }

        $this->mockAtlasUser($payload);
    }
}
